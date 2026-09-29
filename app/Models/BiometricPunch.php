<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One punch imported from the biometric device. date / time are kept as plain
 * Y-m-d / H:i:s strings so a day's punches group and sort without casting.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $date
 * @property string $time
 */
#[Fillable(['employee_id', 'date', 'time'])]
class BiometricPunch extends Model
{
    /**
     * Store punches (already stored ones are ignored) and rebuild each touched day's attendance
     * from the employee's first and last punch that day.
     *
     * @param  list<array{employee_id: int, date: string, time: string}>  $punches
     * @return array{imported: int, days: int}
     */
    public static function import(array $punches): array
    {
        $days = collect($punches)->map(fn (array $p) => $p['employee_id'].'|'.$p['date'])->unique();

        return DB::transaction(function () use ($punches, $days) {
            $now = now();
            $imported = 0;
            foreach (array_chunk($punches, 500) as $chunk) {
                $imported += self::query()->insertOrIgnore(array_map(fn (array $p) => [...$p, 'created_at' => $now, 'updated_at' => $now], $chunk));
            }

            $employees = Employee::query()->with('shift')->whereKey(collect($punches)->pluck('employee_id')->unique())->get()->keyBy('id');

            foreach ($days as $key) {
                [$employeeId, $date] = explode('|', $key);
                $range = self::query()->where('employee_id', $employeeId)->where('date', $date)
                    ->toBase()->selectRaw('min(time) as first, max(time) as last')->first();
                $first = substr((string) $range?->first, 0, 5);
                $last = substr((string) $range?->last, 0, 5);

                // ponytail: punches group by calendar day, so a night shift's punches after midnight land on the next day.
                AttendanceRecord::recordClockTimes($employees[(int) $employeeId], $date, $first, $last !== $first ? $last : null);
            }

            return ['imported' => $imported, 'days' => $days->count()];
        });
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
