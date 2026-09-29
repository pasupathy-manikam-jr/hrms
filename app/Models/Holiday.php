<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Carbon\CarbonImmutable;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string $category
 * @property bool $is_recurring
 * @property bool $is_half_day
 * @property int|null $created_by
 */
#[Fillable(['name', 'start_date', 'end_date', 'category', 'description', 'is_paid', 'is_half_day', 'is_recurring', 'created_by'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'holidays';

    public const CATEGORIES = ['national', 'religious', 'company-specific', 'regional'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_paid' => 'boolean',
            'is_half_day' => 'boolean',
            'is_recurring' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Holidays that apply to a branch (all holidays when no branch is given).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForBranch(Builder $query, ?int $branchId): void
    {
        $query->when($branchId, fn (Builder $q) => $q->whereHas('branches', fn (Builder $b) => $b->whereKey($branchId)));
    }

    /**
     * Each dated occurrence of the given holidays inside [from, to]; recurring holidays
     * repeat on the same month/day every year from their first year on.
     *
     * @param  Builder<self>  $query
     * @return list<array{holiday: self, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public static function occurrences(Builder $query, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $holidays = $query
            ->where(fn (Builder $q) => $q->where('is_recurring', true)->orWhere(fn (Builder $q) => $q
                ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)))
            ->get();

        $occurrences = [];

        foreach ($holidays as $holiday) {
            $years = $holiday->is_recurring ? range($from->year, $to->year) : [$holiday->start_date->year];

            foreach ($years as $year) {
                $start = $holiday->start_date->setYear($year);
                $end = $start->addDays((int) $holiday->start_date->diffInDays($holiday->end_date));

                if ($year >= $holiday->start_date->year && $start <= $to && $end >= $from) {
                    $occurrences[] = ['holiday' => $holiday, 'start' => $start, 'end' => $end];
                }
            }
        }

        return $occurrences;
    }

    /**
     * Dates (Y-m-d) inside [from, to] covered by a full-day holiday for the branch.
     *
     * @return array<string, true>
     */
    public static function fullDayDates(?int $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];

        foreach (self::occurrences(self::query()->forBranch($branchId)->where('is_half_day', false), $from, $to) as $occurrence) {
            for ($day = $occurrence['start']; $day <= $occurrence['end']; $day = $day->addDay()) {
                $dates[$day->toDateString()] = true;
            }
        }

        return $dates;
    }
}
