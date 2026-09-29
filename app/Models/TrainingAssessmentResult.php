<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_training_id
 * @property int $training_assessment_id
 */
#[Fillable(['employee_training_id', 'training_assessment_id', 'score', 'is_passed', 'feedback', 'assessment_date', 'assessed_by'])]
class TrainingAssessmentResult extends Model
{
    /**
     * @return BelongsTo<EmployeeTraining, $this>
     */
    public function employeeTraining(): BelongsTo
    {
        return $this->belongsTo(EmployeeTraining::class);
    }

    /**
     * @return BelongsTo<TrainingAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(TrainingAssessment::class, 'training_assessment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_training_id' => 'integer',
            'training_assessment_id' => 'integer',
            'score' => 'decimal:2',
            'is_passed' => 'boolean',
            'assessment_date' => 'date:Y-m-d',
        ];
    }
}
