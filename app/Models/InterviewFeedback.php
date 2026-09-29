<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\InterviewFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $interview_id
 * @property int|null $interviewer_id
 * @property int $overall_rating
 * @property string $recommendation
 * @property int|null $created_by
 */
#[Fillable([
    'interview_id', 'interviewer_id', 'technical_rating', 'communication_rating', 'cultural_fit_rating',
    'overall_rating', 'recommendation', 'strengths', 'weaknesses', 'comments', 'created_by',
])]
class InterviewFeedback extends Model
{
    /** @use HasFactory<InterviewFeedbackFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'interview-feedback';

    public const RECOMMENDATIONS = ['Strong Hire', 'Hire', 'Maybe', 'Reject', 'Strong Reject'];

    protected $table = 'interview_feedback';

    /**
     * Without manage-any-interview-feedback: only the feedback the user wrote as the interviewer.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-interview-feedback')) {
            $query->where('interviewer_id', $user->id);
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->can('manage-any-interview-feedback') || $this->interviewer_id === $user->id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_id' => 'integer',
            'interviewer_id' => 'integer',
            'technical_rating' => 'integer',
            'communication_rating' => 'integer',
            'cultural_fit_rating' => 'integer',
            'overall_rating' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
