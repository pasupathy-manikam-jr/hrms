<?php

namespace App\Support\Statutory;

use App\Models\Employee;
use App\Models\Payslip;
use App\Support\Money;

/**
 * The figures for an employee's Form EA (C.P.8A, statement of remuneration from employment) for one year, from the
 * payslips of completed payroll runs paid that year (income is taxed in the year it is received, s25(1) ITA 1967).
 * Amounts are in sen.
 *
 * ponytail: payroll has no bonus/commission, benefits in kind, accommodation or TP1 inputs yet, so B1(b), B3–B6
 * and D4 are always zero; add each with the payroll input that would carry it.
 */
class EaForm
{
    /**
     * @return array{payslips: int, salary: int, allowances: int, gross: int, pcb: int, cp38: int, zakat: int, epf: int, socso: int, exempt: int}|null null when no payslip was paid that year
     */
    public static function figures(Employee $employee, int $year): ?array
    {
        $payslips = Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($run) => $run->where('status', 'completed')->whereYear('pay_date', $year))
            ->get(['basic_salary', 'gross_pay', 'statutory']);

        if ($payslips->isEmpty()) {
            return null;
        }

        $sum = fn (callable $amount) => (int) $payslips->sum($amount);
        $salary = $sum(fn (Payslip $payslip) => Money::toCents($payslip->basic_salary));
        $gross = $sum(fn (Payslip $payslip) => Money::toCents($payslip->gross_pay));
        // Taxable remuneration; payslips from before statutory calculation count in full.
        $taxable = $sum(fn (Payslip $payslip) => $payslip->statutory['wages'] ?? Money::toCents($payslip->gross_pay));
        $statutory = fn (string ...$keys) => $sum(fn (Payslip $payslip) => array_sum(array_map(fn ($key) => $payslip->statutory[$key] ?? 0, $keys)));

        return [
            'payslips' => $payslips->count(),
            'salary' => $salary,
            'allowances' => $taxable - $salary,
            'gross' => $taxable,
            'pcb' => $statutory('pcb'),
            'cp38' => $statutory('cp38'),
            'zakat' => $statutory('zakat'),
            'epf' => $statutory('epf_employee'),
            // SOCSO relief covers SOCSO (with Lindung 24 Jam) and EIS contributions.
            'socso' => $statutory('socso_employee', 'skbbk', 'eis_employee'),
            'exempt' => $gross - $taxable,
        ];
    }
}
