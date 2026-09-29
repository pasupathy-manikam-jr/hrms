<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_id
 * @property string $assessment_name
 * @property Carbon $assessment_date
 * @property string|null $score
 * @property string $max_score
 * @property string $pass_fail_status
 * @property string|null $comments
 * @property int|null $conducted_by
 * @property int|null $created_by
 */
#[Fillable(['candidate_id', 'assessment_name', 'assessment_date', 'score', 'max_score', 'comments', 'conducted_by', 'created_by'])]
class CandidateAssessment extends Model
{
    use HasCreator;

    public const MODULE = 'candidate-assessments';

    public const STATUSES = ['Pass', 'Fail', 'Pending'];

    /** Minimum percentage of max_score that counts as a pass (every demo record fits 70%). */
    public const PASS_MARK = 70;

    protected static function booted(): void
    {
        static::saving(fn (self $assessment) => $assessment->pass_fail_status = self::resultFor($assessment->score, $assessment->max_score));
    }

    public static function resultFor(int|float|string|null $score, int|float|string|null $maxScore): string
    {
        if ($score === null || $score === '' || ! (float) $maxScore) {
            return 'Pending';
        }

        return (float) $score * 100 >= self::PASS_MARK * (float) $maxScore ? 'Pass' : 'Fail';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_date' => 'date:Y-m-d',
            'candidate_id' => 'integer',
            'conducted_by' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }
}
