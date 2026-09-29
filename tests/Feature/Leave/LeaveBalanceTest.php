<?php

namespace Tests\Feature\Leave;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balances_are_computed_from_applications_and_adjustments()
    {
        $type = LeaveType::factory()->create(['max_days_per_year' => 21]);
        LeaveType::factory()->create(['status' => 'inactive']);
        $employee = Employee::factory()->create();
        $apply = fn (string $status, string $start, int $days) => LeaveApplication::factory()->create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'status' => $status, 'start_date' => $start, 'end_date' => $start, 'total_days' => $days,
        ]);
        $apply('approved', '2030-02-04', 5);
        $apply('approved', '2030-05-06', 3);
        $apply('pending', '2030-06-03', 2);
        $apply('rejected', '2030-07-01', 4);
        $apply('approved', '2031-01-06', 7); // another year
        LeaveBalanceAdjustment::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2030, 'carried_forward' => 2, 'manual_adjustment' => -1]);

        $this->travelTo('2030-09-01');

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.leave-balances.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/leave-balances/index')
                ->where('year', 2030)
                ->has('leaveTypes', 1)
                ->has('employeeBalances.data', 1)
                ->where('employeeBalances.data.0.balances.0', [
                    'leave_type_id' => $type->id, 'allocated' => 22, 'carried_forward' => 2, 'manual_adjustment' => -1,
                    'adjustment_reason' => null, 'used' => 8, 'pending' => 2, 'remaining' => 12,
                ]));

        $this->get(route('hr.leave-balances.index', ['year' => 2031]))
            ->assertInertia(fn ($page) => $page
                ->where('employeeBalances.data.0.balances.0.used', 7)
                ->where('employeeBalances.data.0.balances.0.remaining', 14));
    }

    public function test_employees_only_see_their_own_balances_and_cannot_adjust()
    {
        $user = $this->userWithRole('employee');
        $own = Employee::factory()->create(['user_id' => $user->id]);
        $other = Employee::factory()->create();
        $type = LeaveType::factory()->create();
        $this->actingAs($user);

        $this->get(route('hr.leave-balances.index'))
            ->assertInertia(fn ($page) => $page
                ->has('employeeBalances.data', 1)
                ->where('employeeBalances.data.0.id', $own->id));

        $this->put(route('hr.leave-balances.adjust'), [
            'employee_id' => $other->id, 'leave_type_id' => $type->id, 'year' => 2030, 'carried_forward' => 0, 'manual_adjustment' => 10,
        ])->assertForbidden();
        $this->assertDatabaseCount('leave_balance_adjustments', 0);
    }

    public function test_hr_can_adjust_a_balance()
    {
        $employee = Employee::factory()->create();
        $type = LeaveType::factory()->create();
        $this->actingAs($this->userWithRole('hr'));
        $adjust = fn (array $data) => $this->put(route('hr.leave-balances.adjust'), [
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2030, 'carried_forward' => 0, 'manual_adjustment' => 0, ...$data,
        ]);

        $adjust(['carried_forward' => -1, 'manual_adjustment' => 999])->assertSessionHasErrors(['carried_forward', 'manual_adjustment']);
        $adjust(['manual_adjustment' => 3, 'adjustment_reason' => 'Overtime'])->assertSessionHasNoErrors();
        $adjust(['manual_adjustment' => -2, 'carried_forward' => 4])->assertSessionHasNoErrors();

        $adjustment = LeaveBalanceAdjustment::sole();
        $this->assertSame(-2, $adjustment->manual_adjustment);
        $this->assertSame(4, $adjustment->carried_forward);
    }
}
