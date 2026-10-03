<?php

namespace Tests\Feature\Payroll;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatutoryPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function processRun(string $month): PayrollRun
    {
        $start = now()->parse($month)->startOfMonth();
        $run = PayrollRun::factory()->create([
            'title' => $start->format('F Y'),
            'pay_period_start' => $start->toDateString(),
            'pay_period_end' => $start->copy()->endOfMonth()->toDateString(),
        ]);
        $run->process();

        return $run;
    }

    public function test_employee_statutory_details_are_validated_and_saved()
    {
        $designation = Designation::factory()->create();
        $this->actingAs($this->userWithRole());
        $payload = [
            'name' => 'Nora Quinn', 'email' => 'nora@example.com', 'password' => 'Zx123456',
            'branch_id' => $designation->department->branch_id, 'department_id' => $designation->department_id,
            'designation_id' => $designation->id, 'date_of_joining' => '2026-01-15', 'employment_type' => 'Full-time',
            'employee_status' => 'active',
        ];

        $this->post(route('hr.employees.store'), [...$payload, 'citizenship' => 'alien', 'marital_status' => 'engaged', 'tax_children' => -1, 'epf_number' => 'EPF-1', 'tax_resident' => 'maybe'])
            ->assertSessionHasErrors(['citizenship', 'marital_status', 'tax_children', 'epf_number', 'tax_resident']);

        $this->post(route('hr.employees.store'), [
            ...$payload, 'citizenship' => 'permanent_resident', 'marital_status' => 'married', 'spouse_working' => '0',
            'tax_children' => '5', 'tax_resident' => '1', 'epf_number' => '12345678', 'lindung24_opt_out' => '1',
        ])->assertSessionHasNoErrors();

        $employee = Employee::query()->where('epf_number', '12345678')->firstOrFail();
        $this->assertSame(['permanent_resident', 'married', false, 5, true, true], [
            $employee->citizenship, $employee->marital_status, $employee->spouse_working, $employee->tax_children, $employee->tax_resident, $employee->lindung24_opt_out,
        ]);
    }

    /**
     * LHDN's worked example (MTD specification 2026, Exhibit 5) through real payroll runs: married, wife working,
     * 3 children, RM5,500 a month → EPF RM605 (employer 12% above RM5,000: RM660); PCB RM110.00 in January and again in February, using January's payslip.
     */
    public function test_payroll_runs_deduct_pcb_as_in_lhdn_example_using_earlier_payslips()
    {
        $employee = Employee::factory()->create(['date_of_birth' => '1990-05-01', 'id_type' => 'mykad', 'marital_status' => 'married', 'spouse_working' => true, 'tax_children' => 3]);
        EmployeeSalary::factory()->create(['employee_id' => $employee->id, 'basic_salary' => '5500.00']);

        $january = $this->processRun('2026-01-01')->payslips()->firstOrFail();
        $this->assertSame([60500, 66000, 11000, 3], [$january->statutory['epf_employee'], $january->statutory['epf_employer'], $january->statutory['pcb'], $january->statutory['pcb_category']]);
        $this->assertContains(['name' => 'PCB (Monthly Tax Deduction)', 'amount' => '110.00'], $january->deductions);
        // No Lindung 24 Jam before June 2026 wages.
        $this->assertSame(0, $january->statutory['skbbk']);

        $february = $this->processRun('2026-02-01')->payslips()->firstOrFail();
        $this->assertSame(11000, $february->statutory['pcb']);
    }

    public function test_foreign_workers_and_opted_out_employees()
    {
        $foreigner = Employee::factory()->create(['date_of_birth' => '1995-01-01', 'id_type' => 'passport', 'lindung24_opt_out' => true]);
        $optedOut = Employee::factory()->create(['date_of_birth' => '1995-01-01', 'id_type' => 'mykad', 'lindung24_opt_out' => true]);
        EmployeeSalary::factory()->create(['employee_id' => $foreigner->id, 'basic_salary' => '1751.00']);
        EmployeeSalary::factory()->create(['employee_id' => $optedOut->id, 'basic_salary' => '3000.00']);

        $run = $this->processRun('2026-07-01');
        $foreign = $run->payslips()->where('employee_id', $foreigner->id)->firstOrFail()->statutory;
        $local = $run->payslips()->where('employee_id', $optedOut->id)->firstOrFail()->statutory;

        // Foreign worker: EPF 2% each rounded up (RM35.02 → RM36), SOCSO Second Category, no EIS, Lindung 24 Jam compulsory.
        $this->assertSame([3600, 3600, 2, 0, 0], [$foreign['epf_employee'], $foreign['epf_employer'], $foreign['socso_category'], $foreign['socso_employee'], $foreign['eis_employee']]);
        $this->assertGreaterThan(0, $foreign['skbbk']);
        // A Malaysian who opted out pays no Lindung 24 Jam but still SOCSO and EIS.
        $this->assertSame([0, 1], [$local['skbbk'], $local['socso_category']]);
        $this->assertGreaterThan(0, $local['eis_employee']);
    }
}
