<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_review_id
 * @property int $performance_indicator_id
 * @property string $rating
 */
#[Fillable(['employee_review_id', 'performance_indicator_id', 'rating', 'comments'])]
class EmployeeReviewRating extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['employee_review_id' => 'integer', 'performance_indicator_id' => 'integer', 'rating' => 'float'];
    }

    /**
     * @return BelongsTo<PerformanceIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class, 'performance_indicator_id');
    }
}
