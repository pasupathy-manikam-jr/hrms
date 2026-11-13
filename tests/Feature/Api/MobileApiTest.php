<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementResponse;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\LoginHistory;
use App\Models\Meeting;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): Employee
    {
        return Employee::factory()->create([
            'user_id' => $this->userWithRole('employee')->id,
            'shift_id' => Shift::factory()->create()->id,
        ]);
    }

    private function login(array $overrides = []): TestResponse
    {
        return $this->postJson(route('api.login'), [
            'email' => 'emp@example.com', 'password' => 'password', 'device_name' => 'Pixel', ...$overrides,
        ]);
    }

    public function test_login_issues_a_token_that_reaches_the_profile_and_logout_revokes_it()
    {
        $employee = $this->employee();
        $employee->user->update(['email' => 'emp@example.com']);

        $this->login(['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');

        $token = $this->login()->assertOk()
            ->assertJsonPath('user.employee.id', $employee->id)
            ->assertJsonPath('user.email', 'emp@example.com')
            ->json('token');
        $this->assertContains('clock-in-out', $this->login()->json('user.permissions'));
        $this->assertSame(2, LoginHistory::query()->where('user_id', $employee->user_id)->count());

        $this->withToken($token)->getJson(route('api.me'))->assertOk()->assertJsonPath('name', $employee->user->name);
        $this->withToken($token)->postJson(route('api.logout'))->assertOk();
        $this->assertSame(1, $employee->user->tokens()->count());
    }

    public function test_login_refuses_inactive_users_and_requires_the_two_factor_code_when_enabled()
    {
        $user = $this->userWithRole('employee');
        $user->update(['email' => 'emp@example.com', 'status' => 'inactive']);
        $this->login()->assertUnprocessable()->assertJsonValidationErrors('email');

        $user->forceFill([
            'status' => 'active',
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('SECRET'),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $provider = $this->mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')->with('SECRET', '123456')->andReturnTrue();
        $provider->shouldReceive('verify')->with('SECRET', '000000')->andReturnFalse();

        $this->login()->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->login(['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->login(['code' => '123456'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_requests_need_a_token_and_deactivated_users_lose_theirs()
    {
        $this->getJson(route('api.me'))->assertUnauthorized();

        $user = $this->userWithRole('employee');
        $token = $user->createToken('Pixel')->plainTextToken;
        $user->update(['status' => 'inactive']);

        $this->withToken($token)->getJson(route('api.me'))->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_employee_clocks_in_and_out_and_sees_their_history()
    {
        $employee = $this->employee();
        Sanctum::actingAs($employee->user);

        Carbon::setTestNow('2026-09-16 09:10:00');
        $this->getJson(route('api.attendance.today'))->assertOk()->assertJsonPath('record', null);
        $this->postJson(route('api.attendance.clock-in'))->assertCreated()->assertJsonPath('record.status', 'present');
        $this->postJson(route('api.attendance.clock-in'))->assertUnprocessable()->assertJsonValidationErrors('attendance');

        Carbon::setTestNow('2026-09-16 18:05:00');
        $this->postJson(route('api.attendance.clock-out'))->assertOk()->assertJsonPath('record.is_early_departure', false);

        $this->getJson(route('api.attendance.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('total', 1);
    }

    public function test_employee_applies_lists_and_withdraws_only_their_own_leave()
    {
        $employee = $this->employee();
        $type = LeaveType::factory()->create(['max_days_per_year' => 20]);
        $other = LeaveApplication::factory()->create(['status' => 'pending']);
        Sanctum::actingAs($employee->user);

        $this->getJson(route('api.leave.types'))->assertOk()->assertJsonPath('0.remaining', 20);

        $id = $this->postJson(route('api.leave.store'), [
            'employee_id' => $other->employee_id,
            'leave_type_id' => $type->id,
            'start_date' => '2030-03-04',
            'end_date' => '2030-03-05',
        ])->assertCreated()->assertJsonPath('application.employee_id', $employee->id)->json('application.id');

        $this->postJson(route('api.leave.store'), ['leave_type_id' => $type->id, 'start_date' => '2030-03-05', 'end_date' => '2030-03-05'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->getJson(route('api.leave.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);

        $this->deleteJson(route('api.leave.destroy', $other->id))->assertNotFound();
        $this->deleteJson(route('api.leave.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('leave_applications', ['id' => $id]);
        $this->assertModelExists($other);
    }

    public function test_employee_sees_only_their_payslips_from_completed_runs()
    {
        $employee = $this->employee();
        $payslip = fn (Employee $e, string $status) => Payslip::create([
            'payroll_run_id' => tap(PayrollRun::factory()->create()->forceFill(['status' => $status]))->save()->id,
            'employee_id' => $e->id, 'payslip_number' => fake()->unique()->numerify('PS-####'),
            'basic_salary' => 1000, 'total_earnings' => 1000, 'gross_pay' => 1000, 'total_deductions' => 0, 'net_pay' => 1000,
            'earnings' => [], 'deductions' => [], 'status' => 'generated',
        ]);
        $mine = $payslip($employee, 'completed');
        $draft = $payslip($employee, 'draft');
        $theirs = $payslip(Employee::factory()->create(), 'completed');
        Sanctum::actingAs($employee->user);

        $this->getJson(route('api.payslips.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson(route('api.payslips.show', $mine->id))->assertOk()->assertJsonPath('net_pay', '1000.00');
        $this->getJson(route('api.payslips.show', $draft->id))->assertNotFound();
        $this->getJson(route('api.payslips.show', $theirs->id))->assertNotFound();

        $this->get(route('api.payslips.pdf', $mine->id))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame('downloaded', $mine->fresh()->status);
        $this->getJson(route('api.payslips.pdf', $theirs->id))->assertNotFound();
    }

    public function test_employee_answers_an_open_survey_once()
    {
        $campaign = EngagementCampaign::create(['name' => 'Pulse', 'status' => 'open', 'min_group_size' => 5]);
        $campaign->syncStatements();
        $closed = EngagementCampaign::create(['name' => 'Old', 'status' => 'closed', 'min_group_size' => 5]);
        $statementIds = $campaign->statements()->pluck('engagement_statements.id');
        $answers = $statementIds->mapWithKeys(fn ($id) => [$id => 4])->all();
        $employee = $this->employee();
        Sanctum::actingAs($employee->user);

        $this->getJson(route('api.surveys.index'))->assertOk()->assertJsonCount(1)->assertJsonPath('0.can_answer', true);
        $this->getJson(route('api.surveys.show', $campaign->id))->assertOk()
            ->assertJsonPath('statements.0.id', $statementIds->first())->assertJsonCount(4, 'ratings');
        $this->getJson(route('api.surveys.show', $closed->id))->assertUnprocessable()->assertJsonValidationErrors('campaign');

        $this->postJson(route('api.surveys.store', $campaign->id), ['answers' => []])->assertUnprocessable();
        $this->postJson(route('api.surveys.store', $campaign->id), ['answers' => $answers])->assertCreated();
        $this->postJson(route('api.surveys.store', $campaign->id), ['answers' => $answers])->assertUnprocessable()->assertJsonValidationErrors('campaign');

        $this->assertSame(1, EngagementResponse::query()->count());
        $this->getJson(route('api.surveys.index'))->assertJsonPath('0.can_answer', false);
    }

    public function test_a_leave_decision_lands_in_the_notification_list_and_the_private_channel_is_signed_for_its_owner()
    {
        $this->withoutDefer();
        $employee = $this->employee();
        $application = LeaveApplication::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($this->userWithRole('hr'))->put(route('hr.leave-applications.approve', $application))->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($employee->user);

        $id = $this->getJson(route('api.notifications.index'))->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.title', 'Leave approved')
            ->assertJsonPath('data.0.body', "{$application->leaveType->name}, {$application->start_date->toDateString()} to {$application->end_date->toDateString()}")
            ->assertJsonPath('data.0.target', ['type' => 'leave', 'id' => $application->id])
            ->json('data.0.id');
        $this->postJson(route('api.notifications.read'), ['ids' => [$id]])->assertNoContent();
        $this->getJson(route('api.notifications.index'))->assertJsonPath('unread_count', 0)->assertJsonPath('data.0.unread', false);

        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'key', 'secret' => 'secret', 'app_id' => '1', 'options' => ['host' => 'localhost'],
        ]]);
        require base_path('routes/channels.php'); // register the channel rules on the reverb driver
        $this->postJson(route('api.broadcasting.auth'), ['socket_id' => '1.1', 'channel_name' => "private-App.Models.User.{$employee->user_id}"])
            ->assertOk()->assertJsonStructure(['auth']);
        $this->postJson(route('api.broadcasting.auth'), ['socket_id' => '1.1', 'channel_name' => 'private-App.Models.User.'.($employee->user_id + 1)])
            ->assertForbidden();
    }

    public function test_announcements_default_to_active_and_meetings_follow_the_web_visibility()
    {
        $employee = $this->employee();
        $announcement = Announcement::factory()->create();
        Announcement::factory()->create(['end_date' => today()->subDay()->toDateString()]);
        $mine = Meeting::factory()->create(['organizer_id' => $employee->user_id]);
        Meeting::factory()->create();
        Sanctum::actingAs($employee->user);

        $this->getJson(route('api.announcements.index'))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(route('api.announcements.show', $announcement->id))->assertOk()->assertJsonPath('title', $announcement->title);
        $this->assertTrue($announcement->viewers()->whereKey($employee->user_id)->exists());

        $this->getJson(route('api.meetings.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_users_without_the_permissions_are_denied()
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.attendance.clock-in'))->assertForbidden();
        $this->getJson(route('api.leave.index'))->assertForbidden();
        $this->getJson(route('api.payslips.index'))->assertForbidden();
        $this->getJson(route('api.announcements.index'))->assertForbidden();
        $this->getJson(route('api.meetings.index'))->assertForbidden();
    }
}
