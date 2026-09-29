<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveApplicationTest extends TestCase
{
    use RefreshDatabase;

    // 2030-03-04 is a Monday.
    private const MONDAY = '2030-03-04';

    private function employeeFor(User $user): Employee
    {
        return Employee::factory()->create(['user_id' => $user->id]);
    }

    public function test_staff_see_every_application_with_filters_and_status_counts()
    {
        $annual = LeaveType::factory()->create();
        $mine = LeaveApplication::factory()->create(['leave_type_id' => $annual->id]);
        LeaveApplication::factory()->create(['status' => 'approved']);
        LeaveApplication::factory()->create(['status' => 'rejected']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.leave-applications.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/leave-applications/index')
                ->has('leaveApplications.data', 3)
                ->has('employees', 3)
                ->where('statusCounts', ['all' => 3, 'pending' => 1, 'approved' => 1, 'rejected' => 1]));

        $this->get(route('hr.leave-applications.index', ['status' => 'approved']))
            ->assertInertia(fn ($page) => $page->has('leaveApplications.data', 1)->where('leaveApplications.data.0.status', 'approved'));

        $this->get(route('hr.leave-applications.index', ['leave_type_id' => $annual->id]))
            ->assertInertia(fn ($page) => $page->has('leaveApplications.data', 1)->where('leaveApplications.data.0.id', $mine->id));

        $this->get(route('hr.leave-applications.index', ['employee_id' => $mine->employee_id]))
            ->assertInertia(fn ($page) => $page->has('leaveApplications.data', 1)->where('leaveApplications.data.0.id', $mine->id));
    }

    public function test_week_calendar_shows_approved_leave_overlapping_the_week()
    {
        $overlapping = LeaveApplication::factory()->create(['status' => 'approved', 'start_date' => '2030-03-01', 'end_date' => '2030-03-05']);
        LeaveApplication::factory()->create(['status' => 'pending', 'start_date' => self::MONDAY, 'end_date' => self::MONDAY]);
        LeaveApplication::factory()->create(['status' => 'approved', 'start_date' => '2030-03-11', 'end_date' => '2030-03-12']);

        $this->actingAs($this->userWithRole('hr'))
            // Any day of the week opens that Monday-to-Sunday week.
            ->get(route('hr.leave-applications.index', ['week_start' => '2030-03-06']))
            ->assertInertia(fn ($page) => $page
                ->where('weekStart', self::MONDAY)
                ->has('calendarRows', 3)
                ->has('calendarLeaves', 1)
                ->where('calendarLeaves.0.id', $overlapping->id));

        $this->get(route('hr.leave-applications.index', ['week_start' => self::MONDAY, 'calendar_employee_id' => $overlapping->employee_id]))
            ->assertInertia(fn ($page) => $page->has('calendarRows', 1)->where('calendarRows.0.id', $overlapping->employee_id));

        $this->get(route('hr.leave-applications.index', ['week_start' => 'soon']))->assertSessionHasErrors('week_start');
    }

    public function test_employees_only_see_their_own_applications()
    {
        $user = $this->userWithRole('employee');
        $own = LeaveApplication::factory()->create(['employee_id' => $this->employeeFor($user)->id]);
        $other = LeaveApplication::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.leave-applications.index'))
            ->assertInertia(fn ($page) => $page
                ->has('leaveApplications.data', 1)
                ->where('leaveApplications.data.0.id', $own->id)
                ->where('employees', [])
                ->has('calendarRows', 1)
                ->where('calendarRows.0.id', $own->employee_id)
                ->where('statusCounts.all', 1));

        $this->put(route('hr.leave-applications.update', $other), [])->assertNotFound();
        $this->delete(route('hr.leave-applications.destroy', $other))->assertNotFound();
        $this->assertModelExists($other);
    }

    public function test_employee_applies_for_themselves_and_total_days_skips_non_working_days()
    {
        $user = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);
        $someoneElse = Employee::factory()->create();
        $type = LeaveType::factory()->create(['max_days_per_year' => 20]);

        // Monday to the next Monday: 6 working days under Mon-Fri, whatever the client claims.
        $this->actingAs($user)->post(route('hr.leave-applications.store'), [
            'employee_id' => $someoneElse->id,
            'leave_type_id' => $type->id,
            'start_date' => self::MONDAY,
            'end_date' => '2030-03-11',
            'total_days' => 1,
            'reason' => 'Family trip',
        ])->assertSessionHasNoErrors();

        $application = LeaveApplication::sole();
        $this->assertSame($employee->id, $application->employee_id);
        $this->assertSame(6, $application->total_days);
        $this->assertSame('pending', $application->status);
    }

    public function test_total_days_follow_the_working_days_setting()
    {
        Setting::put(['workingDays' => [0, 1, 2, 3, 4, 5, 6]]);
        $this->assertSame(8, LeaveApplication::workingDaysBetween(now()->parse(self::MONDAY), now()->parse('2030-03-11')));

        Setting::put(['workingDays' => [1, 3]]);
        $this->assertSame(3, LeaveApplication::workingDaysBetween(now()->parse(self::MONDAY), now()->parse('2030-03-11')));
    }

    public function test_staff_can_apply_on_behalf_of_any_employee()
    {
        $employee = Employee::factory()->create();
        $type = LeaveType::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.leave-applications.store'), ['leave_type_id' => $type->id, 'start_date' => self::MONDAY, 'end_date' => self::MONDAY])
            ->assertSessionHasErrors('employee_id');

        $this->post(route('hr.leave-applications.store'), [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => self::MONDAY, 'end_date' => self::MONDAY,
        ])->assertSessionHasNoErrors();

        $this->assertSame($employee->id, LeaveApplication::sole()->employee_id);
    }

    public function test_dates_overlap_and_balance_are_validated()
    {
        $employee = Employee::factory()->create();
        $type = LeaveType::factory()->create(['max_days_per_year' => 5]);
        $this->actingAs($this->userWithRole());
        $apply = fn (string $start, string $end, array $extra = []) => $this->post(route('hr.leave-applications.store'), [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => $start, 'end_date' => $end, ...$extra,
        ]);

        $apply('2030-03-06', self::MONDAY)->assertSessionHasErrors('end_date');
        $apply('2030-03-09', '2030-03-10')->assertSessionHasErrors('end_date'); // a weekend only

        $apply(self::MONDAY, '2030-03-06')->assertSessionHasNoErrors(); // 3 days, pending
        $apply('2030-03-06', '2030-03-07')->assertSessionHasErrors('start_date'); // overlaps the pending one

        // 2 of 5 days left: pending days already count against the balance.
        $apply('2030-03-11', '2030-03-13')->assertSessionHasErrors('leave_type_id');
        $apply('2030-03-11', '2030-03-12')->assertSessionHasNoErrors();

        // Rejected leave frees its dates and days.
        LeaveApplication::query()->where('start_date', self::MONDAY)->update(['status' => 'rejected']);
        $apply('2030-03-05', '2030-03-07')->assertSessionHasNoErrors();

        // Manual adjustments raise the allocation.
        LeaveBalanceAdjustment::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2030, 'manual_adjustment' => 5]);
        $apply('2030-03-18', '2030-03-22')->assertSessionHasNoErrors();
        $this->assertSame(3, LeaveApplication::query()->whereIn('status', LeaveApplication::ACTIVE_STATUSES)->count());
    }

    public function test_policy_limits_apply_and_policies_without_approval_are_auto_approved()
    {
        $employee = Employee::factory()->create();
        $sick = LeaveType::factory()->create(['max_days_per_year' => 10]);
        LeavePolicy::factory()->create(['leave_type_id' => $sick->id, 'max_days_per_application' => 2, 'requires_approval' => false]);
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.leave-applications.store'), [
            'employee_id' => $employee->id, 'leave_type_id' => $sick->id, 'start_date' => self::MONDAY, 'end_date' => '2030-03-06',
        ])->assertSessionHasErrors('end_date');

        $this->post(route('hr.leave-applications.store'), [
            'employee_id' => $employee->id, 'leave_type_id' => $sick->id, 'start_date' => self::MONDAY, 'end_date' => '2030-03-05',
        ])->assertSessionHasNoErrors();

        $this->assertSame('approved', LeaveApplication::sole()->status);
        $this->assertNotNull(LeaveApplication::sole()->leave_policy_id);
    }

    public function test_only_pending_applications_can_be_approved_or_rejected()
    {
        $approver = $this->userWithRole('hr');
        $pending = LeaveApplication::factory()->create();
        $other = LeaveApplication::factory()->create();
        $this->actingAs($approver);

        $this->put(route('hr.leave-applications.approve', $pending), ['manager_comments' => 'Enjoy'])->assertSessionHasNoErrors();
        $pending->refresh();
        $this->assertSame('approved', $pending->status);
        $this->assertSame('Enjoy', $pending->manager_comments);
        $this->assertSame($approver->id, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);

        $this->put(route('hr.leave-applications.reject', $pending))->assertForbidden();
        $this->put(route('hr.leave-applications.update', $pending), [])->assertForbidden();

        $this->put(route('hr.leave-applications.reject', $other), ['manager_comments' => 'Busy week'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $other->fresh()->status);
        $this->put(route('hr.leave-applications.approve', $other))->assertForbidden();
    }

    public function test_approval_rechecks_the_balance()
    {
        $type = LeaveType::factory()->create(['max_days_per_year' => 3]);
        $application = LeaveApplication::factory()->create(['leave_type_id' => $type->id, 'start_date' => '2030-03-04', 'end_date' => '2030-03-06']);
        LeaveBalanceAdjustment::create(['employee_id' => $application->employee_id, 'leave_type_id' => $type->id, 'year' => 2030, 'manual_adjustment' => -1]);

        $this->actingAs($this->userWithRole())
            ->put(route('hr.leave-applications.approve', $application))
            ->assertSessionHasErrors('manager_comments');
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_employees_can_edit_and_withdraw_pending_leave_but_not_approve()
    {
        $user = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);
        $type = LeaveType::factory()->create(['max_days_per_year' => 20]);
        $application = LeaveApplication::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id]);
        $approved = LeaveApplication::factory()->create(['employee_id' => $employee->id, 'status' => 'approved']);
        $this->actingAs($user);

        $this->put(route('hr.leave-applications.approve', $application))->assertForbidden();
        $this->put(route('hr.leave-applications.reject', $application))->assertForbidden();

        $this->put(route('hr.leave-applications.update', $application), [
            'leave_type_id' => $type->id, 'start_date' => self::MONDAY, 'end_date' => '2030-03-05',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $application->fresh()->total_days);

        $this->delete(route('hr.leave-applications.destroy', $approved))->assertForbidden();
        $this->delete(route('hr.leave-applications.destroy', $application))->assertSessionHasNoErrors();
        $this->assertModelMissing($application);
        $this->assertModelExists($approved);
    }

    public function test_users_without_leave_permissions_are_denied()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('hr.leave-applications.index'))
            ->assertForbidden();
    }
}
