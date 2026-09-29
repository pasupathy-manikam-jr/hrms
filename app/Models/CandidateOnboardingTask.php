<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_onboarding_id
 * @property string $task_name
 * @property int $due_day
 * @property Carbon $due_date
 * @property bool $is_required
 * @property string $status
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'candidate_onboarding_id', 'task_name', 'description', 'category', 'assigned_to_role', 'due_day', 'due_date',
    'is_required', 'sort_order', 'status', 'completed_at',
])]
class CandidateOnboardingTask extends Model
{
    public const STATUSES = ['pending', 'completed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_day' => 'integer',
            'due_date' => 'date:Y-m-d',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CandidateOnboarding, $this>
     */
    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(CandidateOnboarding::class, 'candidate_onboarding_id');
    }
}
