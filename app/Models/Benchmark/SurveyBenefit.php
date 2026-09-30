<?php

namespace App\Models\Benchmark;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's answer to one Benefits item: one company-wide value, or separate values for
 * Non-Exec/Executive and Managerial & Above.
 *
 * @property string $item
 * @property bool|null $same_for_all
 * @property string|null $company_value
 * @property string|null $exec_value
 * @property string|null $manager_value
 */
#[Guarded(['id'])]
class SurveyBenefit extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['same_for_all' => 'boolean'];
    }

    /**
     * @return BelongsTo<SurveyParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(SurveyParticipant::class, 'survey_participant_id');
    }
}
