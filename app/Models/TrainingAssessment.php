<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $training_program_id
 * @property string $passing_score
 * @property int|null $created_by
 */
#[Fillable(['training_program_id', 'name', 'description', 'type', 'passing_score', 'criteria', 'created_by'])]
class TrainingAssessment extends Model
{
    use HasCreator;

    public const MODULE = 'training-assessments';

    public const TYPES = ['quiz', 'practical', 'presentation'];

    /**
     * @return BelongsTo<TrainingProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
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
        return ['training_program_id' => 'integer', 'passing_score' => 'decimal:2', 'created_by' => 'integer'];
    }
}
