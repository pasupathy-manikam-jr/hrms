<?php

namespace Tests\Feature\Payroll;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\Setting;
use App\Models\User;
use App\Support\Statutory\EaForm;
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

    /**
     * Row 6 of PERKESO's sample file (Combine SOCSO + EIS text file format v2.1): RM4,431.78 in June 2026.
     */
    public function test_perkeso_file_matches_the_official_sample_row()
    {
        $user = $this->userWithRole();
        $ali = Employee::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Ali bin Abu'])->id,
            'date_of_birth' => '2000-10-04', 'id_type' => 'mykad', 'id_number' => '001004-01-2345',
        ]);
        EmployeeSalary::factory()->create(['employee_id' => $ali->id, 'basic_salary' => '4431.78']);
        $run = $this->processRun('2026-06-01');
        $this->actingAs($user);

        $this->get(route('hr.payroll-runs.perkeso', $run))->assertSessionHas('inertia.flash_data.toast.type', 'error');

        Setting::put(['employerSocsoCode' => 'E1300001111G']);
        $file = $this->get(route('hr.payroll-runs.perkeso', $run))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="perkeso-2026-06.txt"')
            ->getContent();

        $line = rtrim($file, "\r\n");
        $this->assertSame(278, strlen($line));
        $this->assertSame('E1300001111G', substr($line, 0, 12));
        $this->assertSame('001004012345ALI BIN ABU', rtrim(substr($line, 32, 162)));
        $this->assertSame('062026', substr($line, 194, 6));
        $this->assertSame('        443178  7785  2225  0890  0890  3335', substr($line, 200, 44));
    }

    public function test_perkeso_file_needs_every_employees_socso_identity_and_the_export_permission()
    {
        $foreigner = Employee::factory()->create(['date_of_birth' => '1995-01-01', 'id_type' => 'passport', 'id_number' => 'A1234567']);
        EmployeeSalary::factory()->create(['employee_id' => $foreigner->id, 'basic_salary' => '2000.00']);
        $run = $this->processRun('2026-10-01');
        Setting::put(['employerSocsoCode' => 'E1300001111G']);

        $this->actingAs($this->userWithRole('employee'))->get(route('hr.payroll-runs.perkeso', $run))->assertForbidden();

        $this->actingAs($this->userWithRole())->get(route('hr.payroll-runs.perkeso', $run))
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', fn ($message) => str_contains($message, $foreigner->user->name));

        $foreigner->update(['socso_number' => 'SSFW1234567']);
        $line = $this->get(route('hr.payroll-runs.perkeso', $run))->assertOk()->getContent();
        $this->assertSame('SSFW1234567 ', substr($line, 32, 12));
        // Foreign worker: no EIS.
        $this->assertSame('  0000  0000', substr($line, 226, 12));
    }

    public function test_statutory_employer_numbers_are_validated_and_saved()
    {
        $this->actingAs($this->userWithRole());

        $this->put(route('settings.statutory'), ['employerSocsoCode' => 'E13', 'employerEpfNumber' => 'EPF'])
            ->assertSessionHasErrors(['employerSocsoCode', 'employerEpfNumber']);

        $this->put(route('settings.statutory'), ['employerSocsoCode' => 'e1300001111g', 'employerRegistrationNumber' => '202001012345', 'employerEpfNumber' => '12345678', 'employerTaxNumber' => 'e1234567890'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['E1300001111G', 'E1234567890'], [Setting::get('employerSocsoCode'), Setting::get('employerTaxNumber')]);
        $this->actingAs($this->userWithRole('hr'))->put(route('settings.statutory'), ['employerSocsoCode' => 'E1300001111G'])->assertForbidden();
    }

    public function test_pcb_file_lists_employees_with_pcb_under_the_employer_header()
    {
        $nora = Employee::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Nora binti Ahmad'])->id,
            'date_of_birth' => '1990-05-01', 'id_type' => 'mykad', 'id_number' => '900501-14-5678',
            'marital_status' => 'married', 'spouse_working' => true, 'tax_children' => 3,
        ]);
        $lowPaid = Employee::factory()->create(['date_of_birth' => '1990-05-01', 'id_type' => 'mykad', 'id_number' => '900501-14-1111']);
        EmployeeSalary::factory()->create(['employee_id' => $nora->id, 'basic_salary' => '5500.00']);
        EmployeeSalary::factory()->create(['employee_id' => $lowPaid->id, 'basic_salary' => '2000.00']);
        $run = $this->processRun('2026-01-01');
        $this->actingAs($this->userWithRole());

        Setting::put(['employerTaxNumber' => 'E 9123456780']);
        $this->get(route('hr.payroll-runs.pcb', $run))
            ->assertSessionHas('inertia.flash_data.toast.message', fn ($message) => str_contains($message, 'Nora binti Ahmad'));

        // A ten-digit income tax number gets a leading zero.
        $nora->update(['tax_payer_id' => 'SG 2345678901']);
        $lines = explode("\r\n", rtrim($this->get(route('hr.payroll-runs.pcb', $run))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="pcb-2026-01.txt"')
            ->getContent()));

        // Only Nora has PCB (RM110.00, LHDN's Exhibit 5).
        $this->assertSame('H'.'9123456780'.'9123456780'.'202601'.'0000011000'.'00001'.'0000000000'.'00000', $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertSame(57, strlen($lines[0]));
        $this->assertSame(126, strlen($lines[1]));
        $this->assertSame('D02345678901'.str_pad('NORA BINTI AHMAD', 60).str_repeat(' ', 12).'900501145678'.str_repeat(' ', 14).'0001100000000000', $lines[1]);
    }

    /**
     * Two completed months of LHDN's Exhibit 5 employee (RM5,500): RM11,000 salary, PCB 2 × RM110, EPF 2 × RM605.
     * A draft run and a run paid the next year are left out.
     */
    public function test_ea_form_totals_the_years_completed_payslips()
    {
        $user = User::factory()->create(['name' => 'Nora binti Ahmad']);
        $nora = Employee::factory()->create([
            'user_id' => $user->id, 'date_of_birth' => '1990-05-01', 'id_type' => 'mykad', 'id_number' => '900501-14-5678',
            'marital_status' => 'married', 'spouse_working' => true, 'tax_children' => 3,
        ]);
        EmployeeSalary::factory()->create(['employee_id' => $nora->id, 'basic_salary' => '5500.00']);
        foreach (['2026-01-01', '2026-02-01'] as $month) {
            $this->processRun($month)->forceFill(['status' => 'completed', 'pay_date' => now()->parse($month)->addDays(27)])->save();
        }
        $this->processRun('2026-03-01');
        $this->processRun('2026-12-01')->forceFill(['status' => 'completed', 'pay_date' => '2027-01-05'])->save();

        $figures = EaForm::figures($nora, 2026);
        $this->assertSame(2, $figures['payslips']);
        $this->assertSame([1100000, 0, 1100000, 22000, 121000, 0], [$figures['salary'], $figures['allowances'], $figures['gross'], $figures['pcb'], $figures['epf'], $figures['exempt']]);
        $this->assertGreaterThan(0, $figures['socso']);
        $this->assertNull(EaForm::figures($nora, 2025));

        $hr = $this->userWithRole('hr');
        $this->actingAs($hr)->get(route('hr.payslips.ea-form', ['year' => 2026, 'employee_id' => $nora->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('EA-2026-'.$nora->employee_id.'.pdf');
        $this->get(route('hr.payslips.ea-form', ['year' => 2025, 'employee_id' => $nora->id]))->assertSessionHas('inertia.flash_data.toast.type', 'error');

        // An employee always gets their own form, whichever employee they ask for.
        $user->assignRole('employee');
        $other = Employee::factory()->create();
        $this->actingAs($user)->get(route('hr.payslips.ea-form', ['year' => 2026, 'employee_id' => $other->id]))
            ->assertDownload('EA-2026-'.$nora->employee_id.'.pdf');
        $this->actingAs($this->userWithRole('employee'))->get(route('hr.payslips.ea-form', ['year' => 2026]))->assertNotFound();
    }

    /**
     * LHDN's C.P.8D Information Layout (Pin. 2025): 22 pipe-separated fields, ringgit without sen except zakat,
     * MTD and CP38. Same employee and months as the EA form test.
     */
    public function test_cp8d_lists_each_employee_paid_in_the_year()
    {
        $nora = Employee::factory()->create([
            'user_id' => User::factory()->create(['name' => 'Nora binti Ahmad'])->id,
            'date_of_birth' => '1990-05-01', 'id_type' => 'mykad', 'id_number' => '900501-14-5678', 'tax_payer_id' => 'IG12345678090',
            'marital_status' => 'married', 'spouse_working' => true, 'tax_children' => 3, 'employment_type' => 'Full-time',
        ]);
        EmployeeSalary::factory()->create(['employee_id' => $nora->id, 'basic_salary' => '5500.00']);
        foreach (['2026-01-01', '2026-02-01'] as $month) {
            $this->processRun($month)->forceFill(['status' => 'completed', 'pay_date' => now()->parse($month)->addDays(27)])->save();
        }
        $this->actingAs($this->userWithRole());

        $this->get(route('hr.payroll-runs.cp8d', ['year' => 2026]))->assertSessionHas('inertia.flash_data.toast.type', 'error');

        Setting::put(['employerTaxNumber' => 'E 2900030000']);
        $line = rtrim($this->get(route('hr.payroll-runs.cp8d', ['year' => 2026]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="P2900030000_2026.txt"')
            ->getContent());

        $socso = intdiv(EaForm::figures($nora, 2026)['socso'], 100);
        $this->assertSame("Nora binti Ahmad|12345678090|900501145678|3|2|01-05-2050|2|3|6000|11000|||||||1210||220.00|||{$socso}", $line);
        $this->assertCount(22, explode('|', $line));
        $this->get(route('hr.payroll-runs.cp8d', ['year' => 2025]))->assertSessionHas('inertia.flash_data.toast.type', 'error');
    }

    /**
     * LHDN's Exhibit 5 employee (PCB RM110.00) paying RM50 zakat through salary and RM100 a month under a CP38
     * notice that ends in January.
     */
    public function test_zakat_reduces_pcb_and_cp38_is_deducted_until_its_last_month()
    {
        Setting::put(['employerTaxNumber' => 'E 9123456780']);
        $nora = Employee::factory()->create([
            'date_of_birth' => '1990-05-01', 'id_type' => 'mykad', 'id_number' => '900501-14-5678', 'tax_payer_id' => 'IG12345678090',
            'marital_status' => 'married', 'spouse_working' => true, 'tax_children' => 3,
            'zakat_monthly' => '50.00', 'cp38_monthly' => '100.00', 'cp38_until' => '2026-01-31',
        ]);
        EmployeeSalary::factory()->create(['employee_id' => $nora->id, 'basic_salary' => '5500.00']);

        $january = $this->processRun('2026-01-01');
        $payslip = $january->payslips()->firstOrFail();
        $this->assertSame([6000, 5000, 10000], [$payslip->statutory['pcb'], $payslip->statutory['zakat'], $payslip->statutory['cp38']]);
        $this->assertContains(['name' => 'Zakat', 'amount' => '50.00'], $payslip->deductions);
        $this->assertContains(['name' => 'CP38 (LHDN Instruction)', 'amount' => '100.00'], $payslip->deductions);

        $this->actingAs($this->userWithRole());
        $lines = explode("\r\n", rtrim($this->get(route('hr.payroll-runs.pcb', $january))->assertOk()->getContent()));
        $this->assertStringEndsWith('202601'.'0000006000'.'00001'.'0000010000'.'00001', $lines[0]);
        $this->assertStringEndsWith('0000600000010000', $lines[1]);

        // February: the CP38 notice has ended; January's zakat counts towards the year (Z).
        $february = $this->processRun('2026-02-01')->payslips()->firstOrFail()->statutory;
        $this->assertSame([0, 5000], [$february['cp38'], $february['zakat']]);
        $this->assertLessThan(11000, $february['pcb']);

        $this->put(route('hr.employees.update', $nora), ['cp38_monthly' => '100'])->assertSessionHasErrors('cp38_until');
    }
}
