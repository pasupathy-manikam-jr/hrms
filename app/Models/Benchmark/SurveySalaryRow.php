<?php

namespace App\Models\Benchmark;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One job title + level from a company's Salary Data sheet (role-level aggregates, never individuals).
 *
 * @property int $id
 * @property int $survey_participant_id
 * @property string $job_title
 * @property string $job_level
 * @property int $headcount
 * @property int $male
 * @property int $female
 * @property string $median_salary
 */
#[Guarded(['id'])]
class SurveySalaryRow extends Model
{
    public const ALLOWANCES = ['transport', 'meal', 'housing', 'shift', 'phone', 'overtime', 'outstation', 'other'];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shift_based' => 'boolean'];
    }

    /**
     * @return BelongsTo<SurveyParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(SurveyParticipant::class, 'survey_participant_id');
    }
}
