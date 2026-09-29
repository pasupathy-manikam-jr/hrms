<?php

namespace App\Models;

use Database\Factories\EmployeeGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $goal_type_id
 * @property string $title
 * @property int $progress
 * @property string $status
 */
#[Fillable(['employee_id', 'goal_type_id', 'title', 'description', 'start_date', 'end_date', 'target', 'progress', 'status', 'created_by'])]
class EmployeeGoal extends Model
{
    /** @use HasFactory<EmployeeGoalFactory> */
    use HasFactory;

    public const STATUSES = ['not_started', 'in_progress', 'completed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'goal_type_id' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'progress' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * Everything for manage-any-employee-goals; only the signed-in employee's goals otherwise.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-employee-goals')) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->can('manage-own-employee-goals') ? $user->id : 0));
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
     * @return BelongsTo<GoalType, $this>
     */
    public function goalType(): BelongsTo
    {
        return $this->belongsTo(GoalType::class);
    }
}
