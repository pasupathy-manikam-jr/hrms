<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    private User $employeeUser;

    private PayrollRun $completed;

    private PayrollRun $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeeUser = $this->userWithRole('employee');
        $pf = SalaryComponent::factory()->deduction()->percentage('12.00')->create(['name' => 'PF']);
        EmployeeSalary::factory()->create(['basic_salary' => '30000.00', 'employee_id' => Employee::factory()->create(['user_id' => $this->employeeUser->id])])
            ->components()->sync([$pf->id]);
        EmployeeSalary::factory()->count(2)->create();

        $this->completed = PayrollRun::factory()->create(['title' => 'Completed']);
        $this->completed->process();
        $this->completed->forceFill(['status' => 'completed'])->save();

        $this->draft = PayrollRun::factory()->create(['title' => 'Draft']);
        $this->draft->process();
    }

    public function test_company_sees_all_payslips_and_can_filter()
    {
        $this->actingAs($this->userWithRole());

        $this->get(route('hr.payslips.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/payslips/index')
                ->has('payslips.data', 6)
                ->has('employees', 3)
                ->has('payrollRuns', 2));

        $this->get(route('hr.payslips.index', ['payroll_run_id' => $this->draft->id, 'employee_id' => $this->employeeUser->employee->id]))
            ->assertInertia(fn ($page) => $page
                ->has('payslips.data', 1)
                ->where('payslips.data.0.net_pay', '26400.00')
                ->where('payslips.data.0.deductions.0', ['name' => 'PF', 'amount' => '3600.00']));

        $this->get(route('hr.payslips.index', ['status' => 'downloaded']))
            ->assertInertia(fn ($page) => $page->has('payslips.data', 0));
    }

    public function test_employees_see_only_their_own_payslips_from_completed_runs()
    {
        $this->actingAs($this->employeeUser)
            ->get(route('hr.payslips.index'))
            ->assertInertia(fn ($page) => $page
                ->has('payslips.data', 1)
                ->where('payslips.data.0.employee.user.id', $this->employeeUser->id)
                ->where('payslips.data.0.payroll_run.id', $this->completed->id)
                ->where('employees', [])
                ->has('payrollRuns', 1));

        $otherEmployeeId = Employee::query()->where('user_id', '!=', $this->employeeUser->id)->value('id');
        $this->get(route('hr.payslips.index', ['employee_id' => $otherEmployeeId]))
            ->assertInertia(fn ($page) => $page->has('payslips.data', 0));
    }

    public function test_users_without_payslip_permission_are_denied()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('hr.payslips.index'))
            ->assertForbidden();
    }

    public function test_list_shows_one_pay_period_month_at_a_time()
    {
        $this->actingAs($this->userWithRole());
        $lastMonth = now()->subMonthNoOverflow()->format('Y-m');

        $this->get(route('hr.payslips.index'))
            ->assertInertia(fn ($page) => $page->where('selectedMonth', now()->format('Y-m'))->has('payslips.data', 6));

        $this->get(route('hr.payslips.index', ['selected_month' => $lastMonth]))
            ->assertInertia(fn ($page) => $page->where('selectedMonth', $lastMonth)->has('payslips.data', 0)->where('statusCounts.all', 0));

        // A payroll run's own list ignores the month.
        $this->get(route('hr.payslips.index', ['selected_month' => $lastMonth, 'payroll_run_id' => $this->completed->id]))
            ->assertInertia(fn ($page) => $page->has('payslips.data', 3));
    }
}
