<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Carbon\CarbonImmutable;
use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $candidate_id
 * @property int $job_id
 * @property int|null $round_id
 * @property CarbonImmutable $scheduled_date
 * @property string $scheduled_time
 * @property string $status
 * @property bool $feedback_submitted
 * @property int|null $created_by
 */
#[Fillable([
    'candidate_id', 'job_id', 'round_id', 'interview_type_id', 'scheduled_date', 'scheduled_time', 'duration',
    'location', 'meeting_link', 'status', 'feedback_submitted', 'created_by',
])]
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'interviews';

    public const STATUSES = ['Scheduled', 'Completed', 'Cancelled', 'No-show'];

    /**
     * Without manage-any-interviews: the interviews the user scheduled or sits on as an interviewer.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-interviews')) {
            $query->where(fn (Builder $q) => $q
                ->where('created_by', $user->id)
                ->orWhereHas('interviewers', fn (Builder $i) => $i->whereKey($user->id)));
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'duration' => 'integer',
            'feedback_submitted' => 'boolean',
            'candidate_id' => 'integer',
            'job_id' => 'integer',
            'round_id' => 'integer',
            'interview_type_id' => 'integer',
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
     * @return BelongsTo<JobPosting, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }

    /**
     * @return BelongsTo<InterviewRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(InterviewRound::class, 'round_id');
    }

    /**
     * @return BelongsTo<InterviewType, $this>
     */
    public function interviewType(): BelongsTo
    {
        return $this->belongsTo(InterviewType::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'interview_interviewer');
    }

    /**
     * @return HasMany<InterviewFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class);
    }
}
