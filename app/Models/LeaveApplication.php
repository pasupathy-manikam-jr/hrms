<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Database\Factories\LeaveApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property int $total_days
 * @property string $status
 * @property CarbonInterface|null $approved_at
 */
#[Fillable([
    'employee_id', 'leave_type_id', 'leave_policy_id', 'start_date', 'end_date', 'total_days',
    'reason', 'status', 'manager_comments', 'approved_by', 'approved_at',
])]
class LeaveApplication extends Model
{
    /** @use HasFactory<LeaveApplicationFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    /** Statuses that hold days against the balance. */
    public const ACTIVE_STATUSES = ['pending', 'approved'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'leave_policy_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'total_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Days between the two dates (inclusive) that fall on Settings → working days and
     * aren't a full-day holiday for the branch.
     */
    public static function workingDaysBetween(CarbonInterface $start, CarbonInterface $end, ?int $branchId = null): int
    {
        /** @var list<int> $workingDays */
        $workingDays = Setting::get('workingDays');
        $from = CarbonImmutable::parse($start)->startOfDay();
        $to = CarbonImmutable::parse($end)->startOfDay();
        $holidays = Holiday::fullDayDates($branchId, $from, $to);
        $days = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $days += in_array($day->dayOfWeek, $workingDays, true) && ! isset($holidays[$day->toDateString()]) ? 1 : 0;
        }

        return $days;
    }

    /**
     * Everything for manage-any-leave-applications; only the user's own for manage-own-leave-applications.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-leave-applications')) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->can('manage-own-leave-applications') ? $user->id : 0));
        }
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return BelongsTo<LeavePolicy, $this>
     */
    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
