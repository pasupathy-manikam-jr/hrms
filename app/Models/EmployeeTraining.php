<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $training_program_id
 * @property string $status
 */
#[Fillable([
    'employee_id', 'training_program_id', 'training_session_id', 'status', 'assigned_date',
    'completion_date', 'score', 'certification', 'feedback', 'notes', 'assigned_by',
])]
class EmployeeTraining extends Model
{
    public const STATUSES = ['assigned', 'in_progress', 'completed', 'failed'];

    /**
     * Everything for manage-any-employee-trainings; only the user's own for manage-own-employee-trainings.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-employee-trainings')) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->can('manage-own-employee-trainings') ? $user->id : 0));
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
     * @return BelongsTo<TrainingProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    /**
     * @return BelongsTo<TrainingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * @return HasMany<TrainingAssessmentResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(TrainingAssessmentResult::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'training_program_id' => 'integer',
            'training_session_id' => 'integer',
            'assigned_date' => 'date:Y-m-d',
            'completion_date' => 'date:Y-m-d',
            'score' => 'decimal:2',
            'certification' => 'boolean',
        ];
    }
}
