<?php

namespace App\Support\Statutory;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * PERKESO's "Combine SOCSO + EIS Contribution Text File" (format v2.1, 13 February 2026) for one payroll run,
 * uploaded through ASSIST 2.0 and compulsory from October 2026 wages. One fixed-width line of 278 characters per
 * payslip; amounts are in sen.
 *
 * Amounts follow PERKESO's sample file: right-justified, space-padded to the field and at least four digits
 * ("  0590", "  0000").
 */
class PerkesoFile
{
    /**
     * The employees whose line PERKESO would reject: no 12-digit MyKad number, or a foreign worker without an
     * SSFW number. Empty when the file can be generated.
     *
     * @return array<int, string>
     */
    public static function problems(PayrollRun $run): array
    {
        return self::payslips($run)
            ->filter(fn (Payslip $payslip) => self::idNumber($payslip->employee) === null)
            ->map(fn (Payslip $payslip) => $payslip->employee->user->name)
            ->values()
            ->all();
    }

    public static function contents(PayrollRun $run): string
    {
        $employerCode = (string) Setting::get('employerSocsoCode');
        $registration = (string) Setting::get('employerRegistrationNumber');
        $month = $run->pay_period_start->format('mY');

        return self::payslips($run)->map(function (Payslip $payslip) use ($employerCode, $registration, $month) {
            $statutory = $payslip->statutory;

            return self::text($employerCode, 12)
                .self::text($registration, 20)
                .self::text((string) self::idNumber($payslip->employee), 12)
                .self::text(mb_strtoupper($payslip->employee->user->name), 150)
                .$month
                .str_pad((string) $statutory['wages'], 14, ' ', STR_PAD_LEFT)
                .self::amount($statutory['socso_employer'])
                .self::amount($statutory['socso_employee'])
                .self::amount($statutory['eis_employer'])
                .self::amount($statutory['eis_employee'])
                .self::amount($statutory['skbbk'])
                .str_repeat(' ', 34);
        })->implode("\r\n")."\r\n";
    }

    /**
     * Payslips with calculated statutory amounts, in employee order.
     *
     * @return Collection<int, Payslip>
     */
    private static function payslips(PayrollRun $run): Collection
    {
        return $run->payslips()
            ->whereNotNull('statutory')
            ->with('employee.user:id,name')
            ->orderBy('employee_id')
            ->get();
    }

    /**
     * MyKad number without dashes for citizens and permanent residents, the SSFW / SSFDW number for foreign workers.
     */
    private static function idNumber(Employee $employee): ?string
    {
        if (Contributions::citizenship($employee) === Epf::FOREIGNER) {
            return $employee->socso_number ?: null;
        }

        $myKad = preg_replace('/\D/', '', (string) $employee->id_number);

        return strlen($myKad) === 12 ? $myKad : null;
    }

    /**
     * Left-justified, padded or cut to the width; non-ASCII letters transliterated so positions stay fixed.
     */
    private static function text(string $value, int $width): string
    {
        return str_pad(substr(Str::ascii($value), 0, $width), $width);
    }

    private static function amount(int $sen): string
    {
        return str_pad(sprintf('%04d', $sen), 6, ' ', STR_PAD_LEFT);
    }
}
