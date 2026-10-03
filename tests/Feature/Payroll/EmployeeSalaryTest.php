<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_salaries_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));
        $employee = Employee::factory()->create();
        $hra = SalaryComponent::factory()->percentage('40')->create();
        $pf = SalaryComponent::factory()->deduction()->percentage('12')->create();

        $this->post(route('hr.employee-salaries.store'), ['employee_id' => 999, 'basic_salary' => '12.345', 'component_ids' => [999]])
            ->assertSessionHasErrors(['employee_id', 'basic_salary', 'component_ids.0']);

        $this->post(route('hr.employee-salaries.store'), ['employee_id' => $employee->id, 'basic_salary' => '50000', 'component_ids' => [$hra->id, $pf->id], 'is_active' => true])
            ->assertSessionHasNoErrors();
        $salary = EmployeeSalary::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEqualsCanonicalizing([$hra->id, $pf->id], $salary->components()->pluck('id')->all());

        // One salary per employee.
        $this->post(route('hr.employee-salaries.store'), ['employee_id' => $employee->id, 'basic_salary' => '1'])->assertSessionHasErrors('employee_id');

        $this->get(route('hr.employee-salaries.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employee-salaries/index')
                ->has('employeeSalaries.data', 1)
                ->where('employeeSalaries.data.0.gross_pay', '70000.00')
                ->where('employeeSalaries.data.0.net_pay', $salary->load('components', 'employee')->calculate()['net_pay'])
                ->has('employees', 1));

        $this->put(route('hr.employee-salaries.update', $salary), ['employee_id' => $employee->id, 'basic_salary' => '60000.50', 'component_ids' => [$pf->id], 'is_active' => false])
            ->assertSessionHasNoErrors();
        $salary->refresh();
        $this->assertSame(['60000.50', false], [$salary->basic_salary, $salary->is_active]);
        $this->assertSame([$pf->id], $salary->components()->pluck('id')->all());

        $this->delete(route('hr.employee-salaries.destroy', $salary));
        $this->assertModelMissing($salary);
    }

    public function test_employees_see_only_their_own_salary_and_cannot_change_it()
    {
        $user = $this->userWithRole('employee');
        $own = EmployeeSalary::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])]);
        $other = EmployeeSalary::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.employee-salaries.index'))
            ->assertInertia(fn ($page) => $page
                ->has('employeeSalaries.data', 1)
                ->where('employeeSalaries.data.0.id', $own->id)
                ->where('employees', []));

        $this->get(route('hr.employee-salaries.index', ['employee_id' => $other->employee_id]))
            ->assertInertia(fn ($page) => $page->has('employeeSalaries.data', 0));

        $this->post(route('hr.employee-salaries.store'), ['employee_id' => $user->employee->id, 'basic_salary' => '1'])->assertForbidden();
        $this->put(route('hr.employee-salaries.update', $own), ['basic_salary' => '999999'])->assertForbidden();
        $this->delete(route('hr.employee-salaries.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);
    }

    public function test_company_sees_every_salary()
    {
        EmployeeSalary::factory()->count(3)->create();

        $this->actingAs($this->userWithRole())
            ->get(route('hr.employee-salaries.index'))
            ->assertInertia(fn ($page) => $page->has('employeeSalaries.data', 3));
    }

    public function test_salary_can_be_locked_and_unlocked()
    {
        $salary = EmployeeSalary::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.employee-salaries.toggle-status', $salary))->assertSessionHasNoErrors();
        $this->assertFalse($salary->fresh()->is_active);

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('hr.employee-salaries.toggle-status', $salary))
            ->assertForbidden();
    }

    public function test_payroll_calculation_shows_the_payslip_and_attendance_for_a_run()
    {
        $this->withoutVite();
        $salary = EmployeeSalary::factory()->create(['basic_salary' => '5000.00']);
        $this->actingAs($this->userWithRole());

        $this->from(route('hr.employee-salaries.index'))
            ->get(route('hr.employee-salaries.payroll', $salary))
            ->assertRedirect(route('hr.employee-salaries.index'))
            ->assertSessionHas('inertia.flash_data.toast.message', 'No payroll runs found for this employee.');

        $run = PayrollRun::factory()->create();
        $run->process();
        AttendanceRecord::factory()->create(['employee_id' => $salary->employee_id, 'date' => $run->pay_period_start, 'status' => 'present']);
        AttendanceRecord::factory()->create(['employee_id' => $salary->employee_id, 'date' => $run->pay_period_start->addDay(), 'status' => 'half_day']);

        $this->get(route('hr.employee-salaries.payroll', $salary))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employee-salaries/payroll')
                ->where('selectedRunId', $run->id)
                ->where('payslip.basic_salary', '5000.00')
                ->where('attendance.full_present_days', 1)
                ->where('attendance.half_days', 1)
                ->where('attendance.present_days', 1.5));
    }
}
