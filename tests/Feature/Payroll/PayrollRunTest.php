<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRunTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeSalary $salary;

    /**
     * Two payable employees plus three that must be skipped.
     */
    private function seedSalaries(): void
    {
        $hra = SalaryComponent::factory()->percentage('40.00')->create(['name' => 'HRA']);
        $da = SalaryComponent::factory()->percentage('15.00')->create(['name' => 'DA']);
        $transport = SalaryComponent::factory()->create(['name' => 'Transport', 'default_amount' => '2000.00']);
        $pf = SalaryComponent::factory()->deduction()->percentage('12.00')->create(['name' => 'PF']);
        $esi = SalaryComponent::factory()->deduction()->percentage('0.75')->create(['name' => 'ESI']);
        $tax = SalaryComponent::factory()->deduction()->create(['name' => 'Professional Tax', 'default_amount' => '200.00']);
        $inactive = SalaryComponent::factory()->create(['name' => 'Old Bonus', 'default_amount' => '999.00', 'status' => 'inactive']);

        $this->salary = EmployeeSalary::factory()->create(['basic_salary' => '33333.33']);
        $this->salary->components()->sync([$hra->id, $da->id, $transport->id, $pf->id, $esi->id, $tax->id, $inactive->id]);

        EmployeeSalary::factory()->create(['basic_salary' => '50000.00'])->components()->sync([$pf->id]);

        EmployeeSalary::factory()->create(['is_active' => false]);
        EmployeeSalary::factory()->create(['employee_id' => Employee::factory()->create(['employee_status' => 'terminated'])]);
        EmployeeSalary::factory()->create(['employee_id' => Employee::factory()->create(['date_of_joining' => now()->addYear()->toDateString()])]);
    }

    public function test_percentages_are_rounded_half_up_to_the_cent()
    {
        $this->assertSame(1, Money::percentOf(100, '0.50'));       // 0.005 -> 0.01
        $this->assertSame(0, Money::percentOf(100, '0.49'));       // 0.0049 -> 0.00
        $this->assertSame(1333333, Money::percentOf(3333333, '40.00')); // 13333.332 -> 13333.33
        $this->assertSame('1234.50', Money::format(Money::toCents('1234.5')));
    }

    public function test_salary_breakdown_uses_fixed_and_percentage_components()
    {
        $this->seedSalaries();

        $pay = $this->salary->load('components')->calculate(now()->setDate(2026, 7, 1));

        $this->assertSame(['33333.33', '20333.33', '53666.66'], [$pay['basic_salary'], $pay['total_earnings'], $pay['gross_pay']]);
        $this->assertSame([
            ['name' => 'HRA', 'amount' => '13333.33'],
            ['name' => 'DA', 'amount' => '5000.00'],
            ['name' => 'Transport', 'amount' => '2000.00'],
        ], $pay['earnings']);
        // Component deductions first, then the statutory ones worked out from the official tables.
        $this->assertSame([
            ['name' => 'PF', 'amount' => '4000.00'],
            ['name' => 'ESI', 'amount' => '250.00'],
            ['name' => 'Professional Tax', 'amount' => '200.00'],
            ['name' => 'EPF (KWSP)', 'amount' => '3667.00'],
            ['name' => 'SOCSO (PERKESO)', 'amount' => '29.75'],
            ['name' => 'SOCSO Lindung 24 Jam', 'amount' => '44.65'],
            ['name' => 'EIS (SIP)', 'amount' => '11.90'],
        ], array_slice($pay['deductions'], 0, 7));
        $this->assertSame('PCB (Monthly Tax Deduction)', $pay['deductions'][7]['name']);
        $this->assertSame(['epf_employer' => 400000, 'socso_employer' => 10415, 'eis_employer' => 1190], array_intersect_key($pay['statutory'], array_flip(['epf_employer', 'socso_employer', 'eis_employer'])));

        $deducted = array_sum(array_map(fn (array $line) => Money::toCents($line['amount']), $pay['deductions']));
        $this->assertSame([Money::format($deducted), Money::format(5366666 - $deducted)], [$pay['total_deductions'], $pay['net_pay']]);
    }

    public function test_processing_generates_payslips_and_totals_idempotently()
    {
        $this->seedSalaries();
        $run = PayrollRun::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.payroll-runs.process', $run))->assertSessionHasNoErrors();
        $this->post(route('hr.payroll-runs.process', $run));

        $run->refresh();
        $this->assertSame('draft', $run->status);
        $this->assertSame(2, $run->employee_count);
        $this->assertSame('103666.66', $run->total_gross_pay);
        $this->assertSame(Money::toCents($run->total_gross_pay) - Money::toCents($run->total_deductions), Money::toCents($run->total_net_pay));
        $this->assertSame(Money::toCents($run->total_deductions), $run->payslips->sum(fn (Payslip $payslip) => Money::toCents($payslip->total_deductions)));
        $this->assertSame(2, Payslip::count());

        $payslip = $run->payslips()->where('employee_id', $this->salary->employee_id)->firstOrFail();
        $this->assertSame('53666.66', $payslip->gross_pay);
        $this->assertSame(['name' => 'HRA', 'amount' => '13333.33'], $payslip->earnings[0]);
        // EPF on RM33,333.33 (above RM20,000): 11% and 12% of the actual wages, rounded up to the next ringgit.
        $this->assertSame([366700, 400000], [$payslip->statutory['epf_employee'], $payslip->statutory['epf_employer']]);

        // A raise before reprocessing replaces the draft's payslips rather than adding more.
        $this->salary->update(['basic_salary' => '40000.00']);
        $this->post(route('hr.payroll-runs.process', $run));
        $this->assertSame(2, Payslip::count());
        $this->assertSame('40000.00', $run->payslips()->where('employee_id', $this->salary->employee_id)->value('basic_salary'));
    }

    public function test_completed_run_is_locked()
    {
        $this->seedSalaries();
        $run = PayrollRun::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.payroll-runs.complete', $run))->assertSessionHas('inertia.flash_data.toast.type', 'error');
        $this->assertSame('draft', $run->fresh()->status);

        $this->post(route('hr.payroll-runs.process', $run));
        $this->post(route('hr.payroll-runs.complete', $run));
        $this->assertSame('completed', $run->fresh()->status);
        $netPay = $run->fresh()->total_net_pay;

        $this->salary->update(['basic_salary' => '99999.00']);
        $this->post(route('hr.payroll-runs.process', $run));
        $this->put(route('hr.payroll-runs.update', $run), [...$run->only('payroll_frequency'), 'title' => 'Changed', 'pay_period_start' => '2026-01-01', 'pay_period_end' => '2026-01-31', 'pay_date' => '2026-02-05']);
        $this->delete(route('hr.payroll-runs.destroy', $run));

        $run->refresh();
        $this->assertNotSame('Changed', $run->title);
        $this->assertSame($netPay, $run->total_net_pay);
        $this->assertSame('33333.33', $run->payslips()->where('employee_id', $this->salary->employee_id)->value('basic_salary'));
        $this->expectException(\LogicException::class);
        $run->process();
    }

    public function test_runs_can_be_created_updated_listed_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.payroll-runs.store'), ['title' => '', 'payroll_frequency' => 'daily', 'pay_period_start' => '2026-03-31', 'pay_period_end' => '2026-03-01'])
            ->assertSessionHasErrors(['title', 'payroll_frequency', 'pay_period_end', 'pay_date']);
        $this->post(route('hr.payroll-runs.store'), ['title' => 'March 2026 Payroll', 'payroll_frequency' => 'monthly', 'pay_period_start' => '2026-03-01', 'pay_period_end' => '2026-03-31', 'pay_date' => '2026-04-05'])
            ->assertSessionHasNoErrors();

        $run = PayrollRun::where('title', 'March 2026 Payroll')->firstOrFail();
        $this->put(route('hr.payroll-runs.update', $run), ['title' => 'March Payroll', 'payroll_frequency' => 'monthly', 'pay_period_start' => '2026-03-01', 'pay_period_end' => '2026-03-31', 'pay_date' => '2026-04-05'])
            ->assertSessionHasNoErrors();
        $this->assertSame('March Payroll', $run->fresh()->title);

        $this->get(route('hr.payroll-runs.index', ['status' => 'draft']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/payroll-runs/index')
                ->has('payrollRuns.data', 1)
                ->where('statusCounts.draft', 1)
                ->where('statusCounts.completed', 0));

        $this->delete(route('hr.payroll-runs.destroy', $run));
        $this->assertModelMissing($run);
    }

    public function test_monthly_net_pay_sums_completed_runs_of_the_year()
    {
        $make = fn (string $start, string $net, string $status = 'completed') => PayrollRun::factory()->create(['pay_period_start' => $start, 'pay_period_end' => $start, 'pay_date' => $start])
            ->forceFill(['total_net_pay' => $net, 'status' => $status])->save();

        $make('2026-01-01', '1000.10');
        $make('2026-01-16', '2000.20');
        $make('2026-03-01', '500.00');
        $make('2026-03-01', '9999.00', 'draft');
        $make('2025-12-01', '7777.00');

        $trend = PayrollRun::monthlyNetPay(2026);

        $this->assertCount(12, $trend);
        $this->assertSame(['month' => 'Jan', 'netPay' => 3000.3], $trend[0]);
        $this->assertSame(['month' => 'Feb', 'netPay' => 0.0], $trend[1]);
        $this->assertSame(['month' => 'Mar', 'netPay' => 500.0], $trend[2]);
        $this->assertSame('Dec', $trend[11]['month']);
    }

    public function test_employees_cannot_manage_payroll_runs()
    {
        $run = PayrollRun::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.payroll-runs.index'))->assertForbidden();
        $this->post(route('hr.payroll-runs.store'), ['title' => 'X'])->assertForbidden();
        $this->post(route('hr.payroll-runs.process', $run))->assertForbidden();
        $this->post(route('hr.payroll-runs.complete', $run))->assertForbidden();
        $this->delete(route('hr.payroll-runs.destroy', $run))->assertForbidden();
        $this->assertModelExists($run);
    }

    public function test_run_page_shows_totals_and_payslips()
    {
        $this->withoutVite();
        $this->seedSalaries();
        $run = PayrollRun::factory()->create();
        $run->process();

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.payroll-runs.show', $run))
            ->assertInertia(fn ($page) => $page
                ->component('hr/payroll-runs/show')
                ->where('payrollRun.id', $run->id)
                ->where('payrollRun.total_net_pay', $run->fresh()->total_net_pay)
                ->has('payslips', $run->employee_count)
                ->has('payslips.0.employee.user.name')
                ->has('payslips.0.net_pay'));
    }

    public function test_employees_cannot_open_payroll_runs()
    {
        $this->withoutVite();
        $run = PayrollRun::factory()->create();

        $this->actingAs($this->userWithRole('employee'))->get(route('hr.payroll-runs.show', $run))->assertForbidden();
    }
}
