<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmployee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonInterface $date
 * @property float $hours
 * @property string $status
 * @property CarbonInterface|null $approved_at
 */
#[Fillable([
    'employee_id', 'date', 'project', 'description', 'hours', 'start_time', 'end_time', 'is_billable',
    'status', 'manager_comments', 'approved_by', 'approved_at',
])]
class TimeEntry extends Model
{
    use BelongsToEmployee;

    public const MODULE = 'time-entries';

    /** New and edited entries are pending; approving and rejecting go through their own actions. */
    public const STATUSES = ['pending', 'approved', 'rejected'];

    /** The demo's project list (suggestions; any project name is accepted). */
    public const PROJECTS = ['Website Development', 'Database Optimization', 'Bug Fixes', 'Code Review', 'Documentation', 'Testing', 'Mobile App', 'API Integration'];

    public const MAX_DAY_HOURS = 24;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'date' => 'date:Y-m-d',
            'hours' => 'float',
            'is_billable' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * One status for a day's entries in the weekly grid: pending wins, then rejected, else approved.
     *
     * @param  array<array-key, mixed>  $statuses
     */
    public static function summaryStatus(array $statuses): string
    {
        return in_array('pending', $statuses, true) ? 'pending' : (in_array('rejected', $statuses, true) ? 'rejected' : 'approved');
    }
}
