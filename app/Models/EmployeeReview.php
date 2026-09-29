<?php

namespace App\Models;

use Database\Factories\EmployeeReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int|null $reviewer_id
 * @property int $review_cycle_id
 * @property float|null $overall_rating
 * @property Carbon|null $completion_date
 * @property string $status
 */
#[Fillable(['employee_id', 'reviewer_id', 'review_cycle_id', 'review_date', 'completion_date', 'overall_rating', 'comments', 'status', 'created_by'])]
class EmployeeReview extends Model
{
    /** @use HasFactory<EmployeeReviewFactory> */
    use HasFactory;

    public const STATUSES = ['scheduled', 'in_progress', 'completed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'reviewer_id' => 'integer',
            'review_cycle_id' => 'integer',
            'review_date' => 'date:Y-m-d',
            'completion_date' => 'date:Y-m-d',
            'overall_rating' => 'float',
            'created_by' => 'integer',
        ];
    }

    /**
     * Everything for manage-any-employee-reviews; only the signed-in employee's reviews otherwise.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-employee-reviews')) {
            $query->whereHas('employee', fn ($q) => $q->where('user_id', $user->can('manage-own-employee-reviews') ? $user->id : 0));
        }
    }

    /**
     * Save the per-indicator ratings and recompute the overall rating (their mean) from what was stored.
     *
     * @param  array<int|string, array{rating: float|int|string, comments?: string|null}>  $ratings  keyed by indicator id
     */
    public function syncRatings(array $ratings): void
    {
        $this->ratings()->whereNotIn('performance_indicator_id', array_keys($ratings))->delete();

        foreach ($ratings as $indicatorId => $rating) {
            $this->ratings()->updateOrCreate(['performance_indicator_id' => $indicatorId], [
                'rating' => $rating['rating'],
                'comments' => $rating['comments'] ?? null,
            ]);
        }

        $average = $this->ratings()->avg('rating');
        $this->update(['overall_rating' => $average === null ? null : round((float) $average, 2)]);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<ReviewCycle, $this>
     */
    public function reviewCycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class);
    }

    /**
     * @return HasMany<EmployeeReviewRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(EmployeeReviewRating::class);
    }
}
