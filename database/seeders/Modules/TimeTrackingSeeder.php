<?php

namespace Database\Seeders\Modules;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularization;
use App\Models\BiometricPunch;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class TimeTrackingSeeder extends Seeder
{
    /**
     * Seed the demo's timesheet entries, attendance regularizations and biometric punches.
     */
    public function run(): void
    {
        $employees = Employee::query()->pluck('id', 'employee_id');
        $approver = User::query()->where('email', 'company@example.com')->value('id');

        $entries = File::json(database_path('demo/time-entries.json'), JSON_THROW_ON_ERROR);
        // The demo's timesheet week moves to the current week, so the grid opens with data
        // (re-running in the same week adds nothing; a later week gets its own copy).
        $shift = (int) CarbonImmutable::parse(collect($entries)->min('date'))->startOfWeek(CarbonInterface::MONDAY)
            ->diffInDays(today()->toImmutable()->startOfWeek(CarbonInterface::MONDAY));

        foreach ($entries as $row) {
            if (! isset($employees[$row['employee']])) {
                continue;
            }

            $row['date'] = CarbonImmutable::parse($row['date'])->addDays($shift)->toDateString();
            $decided = in_array($row['status'], ['approved', 'rejected'], true);
            TimeEntry::query()->firstOrCreate(
                ['employee_id' => $employees[$row['employee']], 'date' => $row['date'], 'description' => $row['description']],
                [...Arr::except($row, 'employee'), 'approved_by' => $decided ? $approver : null, 'approved_at' => $decided ? now() : null],
            );
        }

        // The demo's pending requests, with the day's current times as the "original".
        foreach (File::json(database_path('demo/attendance-regularizations.json'), JSON_THROW_ON_ERROR) as $row) {
            if (! isset($employees[$row['employee']])) {
                continue;
            }

            $employeeId = $employees[$row['employee']];
            $record = AttendanceRecord::query()->where('employee_id', $employeeId)->whereDate('date', $row['date'])->first();
            AttendanceRegularization::query()->firstOrCreate(
                ['employee_id' => $employeeId, 'date' => $row['date'], 'reason' => $row['reason']],
                [
                    ...Arr::except($row, 'employee'),
                    'status' => 'pending',
                    'attendance_record_id' => $record?->id,
                    'original_clock_in' => $record?->clock_in,
                    'original_clock_out' => $record?->clock_out,
                ],
            );
        }

        // Device IDs, then the demo's punches through the same import as the CSV upload.
        /** @var array{mappings: array<string, string>, punches: list<array{biometric_emp_id: string, timestamp: string}>} $biometric */
        $biometric = File::json(database_path('demo/biometric-attendance.json'), JSON_THROW_ON_ERROR);
        foreach ($biometric['mappings'] as $code => $biometricId) {
            Employee::query()->where('employee_id', $code)->whereNull('biometric_emp_id')->update(['biometric_emp_id' => $biometricId]);
        }

        $ids = Employee::query()->whereNotNull('biometric_emp_id')->pluck('id', 'biometric_emp_id');
        // Whole weeks forward (weekdays stay weekdays) so the demo's punches end by yesterday; not
        // today, which would mark the demo employee as already clocked in.
        $lastDay = CarbonImmutable::parse(substr((string) collect($biometric['punches'])->max('timestamp'), 0, 10));
        $weeks = (int) floor($lastDay->diffInDays(today()->toImmutable()->subDay()) / 7);

        BiometricPunch::import(array_values(collect($biometric['punches'])
            ->filter(fn (array $p) => isset($ids[$p['biometric_emp_id']]))
            ->map(fn (array $p) => [
                'employee_id' => (int) $ids[$p['biometric_emp_id']],
                'date' => CarbonImmutable::parse(substr($p['timestamp'], 0, 10))->addWeeks($weeks)->toDateString(),
                'time' => substr($p['timestamp'], 11),
            ])
            ->all()));
    }
}
