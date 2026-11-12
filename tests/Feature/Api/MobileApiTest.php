<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\Employee;
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
