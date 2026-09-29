<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $training_type_id
 * @property string $name
 * @property string $status
 * @property bool $is_self_enrollment
 * @property int|null $created_by
 */
#[Fillable([
    'training_type_id', 'name', 'description', 'duration', 'cost', 'capacity', 'status',
    'prerequisites', 'is_mandatory', 'is_self_enrollment', 'created_by',
])]
class TrainingProgram extends Model
{
    use HasCreator;

    public const MODULE = 'training-programs';

    public const STATUSES = ['draft', 'active', 'completed', 'cancelled'];

    /**
     * @return BelongsTo<TrainingType, $this>
     */
    public function trainingType(): BelongsTo
    {
        return $this->belongsTo(TrainingType::class);
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    /**
     * @return HasMany<EmployeeTraining, $this>
     */
    public function employeeTrainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class);
    }

    /**
     * @return HasMany<TrainingAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(TrainingAssessment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'training_type_id' => 'integer',
            'duration' => 'integer',
            'cost' => 'decimal:2',
            'capacity' => 'integer',
            'is_mandatory' => 'boolean',
            'is_self_enrollment' => 'boolean',
            'created_by' => 'integer',
        ];
    }
}
