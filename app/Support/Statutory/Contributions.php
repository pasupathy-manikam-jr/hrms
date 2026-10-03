<?php

namespace App\Support\Statutory;

use App\Models\Employee;
use App\Models\Payslip;
use Carbon\CarbonInterface;

/**
 * One employee's statutory deductions and employer contributions for a month: EPF, SOCSO (with Lindung 24 Jam),
 * EIS and PCB. PCB uses what earlier payslips this year already paid. Amounts are in sen.
 */
class Contributions
{
    /**
     * @param  int  $wages  this month's remuneration subject to contributions and tax (sen)
     * @return array{wages: int, epf_employee: int, epf_employer: int, socso_category: int, socso_employee: int, socso_employer: int, skbbk: int, eis_employee: int, eis_employer: int, pcb_category: int, pcb: int}
     */
    public static function for(Employee $employee, int $wages, CarbonInterface $month): array
    {
        $month = $month->copy()->startOfMonth();
        $citizenship = self::citizenship($employee);
        $foreigner = $citizenship === Epf::FOREIGNER;
        $age = $employee->date_of_birth ? (int) $employee->date_of_birth->diffInYears($month) : 0;

        $epf = Epf::contribution($wages, $citizenship, $age);
        // Foreign workers are covered for employment injury only and cannot opt out of Lindung 24 Jam.
        $socso = Socso::contribution($wages, $foreigner || $age >= 60, $foreigner || ! $employee->lindung24_opt_out, $month);
        // EIS covers Malaysian citizens and permanent residents below 60.
        $eis = ! $foreigner && $age < 60 ? Socso::eis($wages) : 0;

        $category = self::taxCategory($employee);
        $earlier = self::earlierThisYear($employee, $month);

        return [
            'wages' => $wages,
            'epf_employee' => $epf['employee'],
            'epf_employer' => $epf['employer'],
            'socso_category' => $socso['category'],
            'socso_employee' => $socso['employee'],
            'socso_employer' => $socso['employer'],
            'skbbk' => $socso['skbbk'],
            'eis_employee' => $eis,
            'eis_employer' => $eis,
            'pcb_category' => $category,
            'pcb' => Pcb::monthly(
                $month->month, $category, (int) $employee->tax_children, (bool) $employee->tax_resident,
                $earlier['wages'], $earlier['epf'], $earlier['pcb'], $wages, $epf['employee'],
            ),
        ];
    }

    /**
     * The employee's lines on the payslip, in the order they appear.
     *
     * @param  array{epf_employee: int, socso_employee: int, skbbk: int, eis_employee: int, pcb: int}  $statutory
     * @return array<string, int>
     */
    public static function deductionLines(array $statutory): array
    {
        return array_filter([
            'EPF (KWSP)' => $statutory['epf_employee'],
            'SOCSO (PERKESO)' => $statutory['socso_employee'],
            'SOCSO Lindung 24 Jam' => $statutory['skbbk'],
            'EIS (SIP)' => $statutory['eis_employee'],
            'PCB (Monthly Tax Deduction)' => $statutory['pcb'],
        ]);
    }

    /**
     * Stored citizenship, else assumed from the ID: MyKad holders are citizens, passport holders foreigners.
     */
    public static function citizenship(Employee $employee): string
    {
        return $employee->citizenship ?? ($employee->id_type === 'passport' ? Epf::FOREIGNER : Epf::CITIZEN);
    }

    /**
     * LHDN category: 1 single, 2 married with a non-working spouse, 3 married with a working spouse, divorced or widowed.
     */
    public static function taxCategory(Employee $employee): int
    {
        return match ($employee->marital_status) {
            'married' => $employee->spouse_working ? 3 : 2,
            'divorced', 'widowed' => 3,
            default => 1,
        };
    }

    /**
     * Remuneration, employee EPF and PCB on this employee's payslips for earlier months of the same year.
     *
     * @return array{wages: int, epf: int, pcb: int}
     */
    private static function earlierThisYear(Employee $employee, CarbonInterface $month): array
    {
        $totals = ['wages' => 0, 'epf' => 0, 'pcb' => 0];

        Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($run) => $run
                ->where('pay_period_start', '>=', $month->copy()->startOfYear()->toDateString())
                ->where('pay_period_start', '<', $month->toDateString()))
            ->get(['gross_pay', 'statutory'])
            ->each(function (Payslip $payslip) use (&$totals) {
                $statutory = $payslip->statutory ?? [];
                $totals['wages'] += $statutory['wages'] ?? (int) round((float) $payslip->gross_pay * 100);
                $totals['epf'] += $statutory['epf_employee'] ?? 0;
                $totals['pcb'] += $statutory['pcb'] ?? 0;
            });

        return $totals;
    }
}
