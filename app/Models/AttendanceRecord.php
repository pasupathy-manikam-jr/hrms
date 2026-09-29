<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One employee's attendance for one day (unique per employee + date).
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property string|null $clock_in
 * @property string|null $clock_out
 * @property float $total_hours
 * @property string $status
 * @property bool $is_late
 * @property bool $is_early_departure
 * @property float $overtime_hours
 * @property string|null $notes
 */
#[Fillable(['employee_id', 'date', 'clock_in', 'clock_out', 'total_hours', 'status', 'is_late', 'is_early_departure', 'overtime_hours', 'notes'])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    public const STATUSES = ['present', 'absent', 'half_day', 'on_leave', 'holiday'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'date' => 'date:Y-m-d',
            'total_hours' => 'float',
            'overtime_hours' => 'float',
            'is_late' => 'boolean',
            'is_early_departure' => 'boolean',
        ];
    }

    /**
     * The current moment in the company's timezone (Settings → System).
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(Setting::get('defaultTimezone'));
    }

    /**
     * Derive late / early departure / hours / overtime from the clock times and the employee's shift.
     * Night shifts wrap past midnight: clocking in after midnight is late, leaving before the shift end is early.
     * ponytail: a grace window that itself crosses midnight (e.g. 23:50 + 15 min) is not handled.
     */
    public function computeTimes(?Shift $shift): static
    {
        $in = $this->clock_in ? substr($this->clock_in, 0, 5) : null;
        $out = $this->clock_out ? substr($this->clock_out, 0, 5) : null;
        $start = $shift ? substr($shift->start_time, 0, 5) : null;
        $end = $shift ? substr($shift->end_time, 0, 5) : null;
        $night = (bool) $shift?->is_night_shift;

        $this->is_late = $shift && $in
            && ($in > CarbonImmutable::parse($start)->addMinutes($shift->grace_period)->format('H:i') || ($night && $in < $end));
        $this->is_early_departure = $shift && $out && ($out < $end || ($night && $out >= $start));

        if (! $in || ! $out) {
            $this->total_hours = 0;
            $this->overtime_hours = 0;

            return $this;
        }

        $break = ($shift->break_duration ?? 0) / 60;
        $this->total_hours = round(max(0, self::span($in, $out) - $break), 2);
        $scheduled = $shift ? max(0, self::span($shift->start_time, $shift->end_time) - $break) : 8;
        $this->overtime_hours = round(max(0, $this->total_hours - $scheduled), 2);

        return $this;
    }

    /**
     * Create or update an employee's day from clock times (approved regularization, biometric import):
     * the day becomes "present" and late / hours / overtime are recomputed from the shift.
     */
    public static function recordClockTimes(Employee $employee, string $date, string $clockIn, ?string $clockOut): self
    {
        $record = self::query()->where('employee_id', $employee->id)->whereDate('date', $date)->first()
            ?? new self(['employee_id' => $employee->id, 'date' => $date]);
        $record->fill(['clock_in' => $clockIn, 'clock_out' => $clockOut, 'status' => 'present']);
        $record->computeTimes($employee->shift)->save();

        return $record;
    }

    /**
     * Hours from one clock time to the next, wrapping past midnight.
     */
    private static function span(string $from, string $to): float
    {
        $minutes = CarbonImmutable::parse($from)->diffInMinutes(CarbonImmutable::parse($to), false);

        return ($minutes < 0 ? $minutes + 1440 : $minutes) / 60;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
