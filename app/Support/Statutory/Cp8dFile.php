<?php

namespace App\Support\Statutory;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\Resignation;
use App\Models\Termination;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * LHDN's C.P.8D for a year of remuneration (C.P.8D Information Layout, Pin. 2025): one pipe-separated line of 22
 * fields per employee paid that year, uploaded through e-Data Praisi / e-CP8D as P<E no.>_<year>.txt. Amounts come
 * from the same figures as Form EA; ringgit fields drop the sen, decimal fields keep it, and absent amounts are
 * left empty as in LHDN's second example.
 *
 * ponytail: "number of children" is the PCB child relief units (a child in higher education counts 4 there); store
 * the head count separately if the two must differ. Status 1 (management) is not distinguished from permanent.
 */
class Cp8dFile
{
    /** LHDN employee status by employment type: 2 permanent, 3 contract, 4 part time, 6 others. */
    private const STATUSES = ['Full-time' => 2, 'Contract' => 3, 'Part-time' => 4];

    /** Minimum Retirement Age Act 2012. */
    private const RETIREMENT_AGE = 60;

    public static function filename(int $year): string
    {
        return 'P'.PcbFile::employerNumber().'_'.$year.'.txt';
    }

    /**
     * Why the file cannot be generated: no E number, nobody paid that year, or employees without a date of birth
     * (needed for the retirement date). Empty when it can.
     *
     * @return array<int, string>
     */
    public static function problems(int $year): array
    {
        if (PcbFile::employerNumber() === null) {
            return [__('Set the LHDN employer number (E no.) in Settings first.')];
        }

        $employees = self::employees($year);

        if ($employees->isEmpty()) {
            return [__('No completed payroll was paid in :year.', ['year' => $year])];
        }

        $missing = $employees->filter(fn (Employee $employee) => self::endDate($employee, $year) === null)
            ->map(fn (Employee $employee) => $employee->user->name);

        return $missing->isEmpty() ? [] : [__('Add the date of birth for: :names', ['names' => $missing->implode(', ')])];
    }

    public static function contents(int $year): string
    {
        return self::employees($year)->map(function (Employee $employee) use ($year) {
            $figures = EaForm::figures($employee, $year);

            if ($figures === null) {
                return null;
            }

            [$myKad, $passport] = PcbFile::identity($employee) ?? ['', ''];

            return implode('|', [
                substr(Str::ascii($employee->user->name), 0, 60),
                PcbFile::taxNumber($employee) ?? '',
                $myKad ?: ($passport ?: '000000000000'),
                Contributions::taxCategory($employee),
                self::STATUSES[$employee->employment_type] ?? 6,
                self::endDate($employee, $year)?->format('d-m-Y'),
                2,
                $employee->tax_children ?: '',
                self::ringgit($employee->tax_children * Pcb::CHILD_RELIEF * 100),
                self::ringgit($figures['gross']),
                '', '', '',
                self::ringgit($figures['exempt']),
                '', '',
                self::ringgit($figures['epf']),
                self::sen($figures['zakat']),
                self::sen($figures['pcb']),
                self::sen($figures['cp38']),
                '',
                self::ringgit($figures['socso']),
            ]);
        })->filter()->implode("\r\n")."\r\n";
    }

    /**
     * Employees with a completed payslip paid that year, by name.
     *
     * @return Collection<int, Employee>
     */
    private static function employees(int $year): Collection
    {
        return Employee::query()
            ->whereIn('id', Payslip::query()->select('employee_id')
                ->whereHas('payrollRun', fn ($run) => $run->where('status', 'completed')->whereYear('pay_date', $year)))
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (Employee $employee) => $employee->user->name)
            ->values();
    }

    /**
     * The day they left that year (termination, or an approved resignation's last working day), else the day they
     * reach the retirement age.
     */
    private static function endDate(Employee $employee, int $year): ?CarbonInterface
    {
        $left = collect([
            Termination::query()->where('employee_id', $employee->id)->whereYear('termination_date', $year)->min('termination_date'),
            Resignation::query()->where('employee_id', $employee->id)->whereIn('status', ['approved', 'completed'])->whereYear('last_working_day', $year)->min('last_working_day'),
        ])->filter()->min();

        if ($left) {
            return Carbon::parse($left);
        }

        return $employee->date_of_birth?->copy()->addYears(self::RETIREMENT_AGE);
    }

    private static function ringgit(int $sen): string
    {
        return $sen > 0 ? (string) intdiv($sen, 100) : '';
    }

    private static function sen(int $sen): string
    {
        return $sen > 0 ? number_format($sen / 100, 2, '.', '') : '';
    }
}
