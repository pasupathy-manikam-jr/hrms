<?php

namespace App\Support\Statutory;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * LHDN's PCB / CP39 text file for one payroll run, uploaded through e-PCB Plus (e-Data PCB). Fixed width:
 *
 *   Header (57)  H · employer E no. HQ (10) · E no. branch (10) · year (4) · month (2) · total PCB (10) ·
 *                PCB records (5) · total CP38 (10) · CP38 records (5)
 *   Detail (126) D · income tax no. (11: ten digits plus the wife code) · name (60) · old IC (12) · new IC (12) ·
 *                passport (12) · country code (2) · PCB (8) · CP38 (8)
 *
 * The lengths are those e-Data PCB's validation enforces (header ≥ 57, detail 126–136; the optional 10-character
 * employee number is left off). Numbers are zero-padded and right-justified, text left-justified; amounts in sen.
 * Only employees with PCB or CP38 deducted are listed; the record counts are of non-zero PCB and CP38 amounts.
 */
class PcbFile
{
    /**
     * Why the file cannot be generated: the employer number, or employees LHDN would reject (no income tax number,
     * or no MyKad / passport number). Empty when it can.
     *
     * @return array<int, string>
     */
    public static function problems(PayrollRun $run): array
    {
        if (self::employerNumber() === null) {
            return [__('Set the LHDN employer number (E no.) in Settings first.')];
        }

        $missing = self::payslips($run)
            ->filter(fn (Payslip $payslip) => self::taxNumber($payslip->employee) === null || self::identity($payslip->employee) === null)
            ->map(fn (Payslip $payslip) => $payslip->employee->user->name);

        return $missing->isEmpty() ? [] : [__('Add the income tax number and MyKad / passport number for: :names', ['names' => $missing->implode(', ')])];
    }

    public static function hasDeductions(PayrollRun $run): bool
    {
        return self::payslips($run)->isNotEmpty();
    }

    public static function contents(PayrollRun $run): string
    {
        $payslips = self::payslips($run);
        $employer = (string) self::employerNumber();
        $amount = fn (Payslip $payslip, string $key) => (int) ($payslip->statutory[$key] ?? 0);
        $pcb = $payslips->map(fn (Payslip $payslip) => $amount($payslip, 'pcb'));
        $cp38 = $payslips->map(fn (Payslip $payslip) => $amount($payslip, 'cp38'));

        $lines = $payslips->map(function (Payslip $payslip) use ($amount) {
            $employee = $payslip->employee;
            [$myKad, $passport] = self::identity($employee) ?? ['', ''];

            return 'D'
                .self::taxNumber($employee)
                .self::text(mb_strtoupper($employee->user->name), 60)
                .self::text('', 12)
                .self::text($myKad, 12)
                .self::text($passport, 12)
                .self::text('', 2)
                .self::number($amount($payslip, 'pcb'), 8)
                .self::number($amount($payslip, 'cp38'), 8);
        });

        $header = 'H'.$employer.$employer
            .$run->pay_period_start->format('Ym')
            .self::number($pcb->sum(), 10).self::number($pcb->filter()->count(), 5)
            .self::number($cp38->sum(), 10).self::number($cp38->filter()->count(), 5);

        return $lines->prepend($header)->implode("\r\n")."\r\n";
    }

    /**
     * Payslips with PCB or CP38 deducted, in employee order.
     *
     * @return Collection<int, Payslip>
     */
    private static function payslips(PayrollRun $run): Collection
    {
        return $run->payslips()
            ->whereNotNull('statutory')
            ->with('employee.user:id,name')
            ->orderBy('employee_id')
            ->get()
            ->filter(fn (Payslip $payslip) => ($payslip->statutory['pcb'] ?? 0) + ($payslip->statutory['cp38'] ?? 0) > 0)
            ->values();
    }

    /**
     * The employer's E number as ten digits (an "E 1234567890" style entry loses its prefix); null when unset.
     */
    public static function employerNumber(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) Setting::get('employerTaxNumber'));

        return $digits === '' || strlen($digits) > 10 ? null : str_pad($digits, 10, '0', STR_PAD_LEFT);
    }

    /**
     * The income tax number's eleven digits without its IG / SG / OG prefix; a ten-digit number gets a leading
     * zero, as LHDN asks.
     */
    public static function taxNumber(Employee $employee): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $employee->tax_payer_id);

        return in_array(strlen($digits), [10, 11], true) ? str_pad($digits, 11, '0', STR_PAD_LEFT) : null;
    }

    /**
     * [new IC, passport]: the 12-digit MyKad number, or the passport number for passport holders.
     *
     * @return array{string, string}|null
     */
    public static function identity(Employee $employee): ?array
    {
        if ($employee->id_type === 'passport') {
            $passport = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $employee->id_number));

            return $passport === '' ? null : ['', substr($passport, 0, 12)];
        }

        $myKad = preg_replace('/\D/', '', (string) $employee->id_number);

        return strlen($myKad) === 12 ? [$myKad, ''] : null;
    }

    private static function text(string $value, int $width): string
    {
        return str_pad(substr(Str::ascii($value), 0, $width), $width);
    }

    private static function number(int $value, int $width): string
    {
        return str_pad((string) $value, $width, '0', STR_PAD_LEFT);
    }
}
