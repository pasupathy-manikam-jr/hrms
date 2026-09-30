<?php

namespace App\Models\Benchmark;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's Attrition & Hiring answers.
 */
#[Guarded(['id'])]
class SurveyAttrition extends Model
{
    protected $table = 'survey_attrition';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['retrenched' => 'boolean'];
    }

    /**
     * @return BelongsTo<SurveyParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(SurveyParticipant::class, 'survey_participant_id');
    }
}
