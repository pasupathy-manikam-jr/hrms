<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $training_program_id
 * @property string $name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string $status
 * @property string|null $location
 * @property int|null $created_by
 */
#[Fillable([
    'training_program_id', 'name', 'start_date', 'end_date', 'location_type', 'location',
    'meeting_link', 'status', 'notes', 'created_by',
])]
class TrainingSession extends Model
{
    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    public const LOCATION_TYPES = ['physical', 'virtual'];

    protected static function booted(): void
    {
        static::creating(fn (self $session) => $session->created_by ??= auth()->user()?->id);
    }

    /**
     * Everything for manage-any-training-sessions; otherwise sessions the user trains,
     * or belonging to a program they are enrolled in.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('manage-any-training-sessions')) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->whereHas('trainers', fn (Builder $t) => $t->where('user_id', $user->id))
            ->orWhereHas('program.employeeTrainings.employee', fn (Builder $e) => $e->where('user_id', $user->id)));
    }

    /**
     * @return BelongsTo<TrainingProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'training_session_trainers');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'training_program_id' => 'integer',
            'start_date' => 'datetime:Y-m-d\TH:i',
            'end_date' => 'datetime:Y-m-d\TH:i',
            'created_by' => 'integer',
        ];
    }
}
