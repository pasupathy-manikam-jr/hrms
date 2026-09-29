<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $checklist_id
 * @property string $task_name
 * @property string|null $description
 * @property string $category
 * @property string|null $assigned_to_role
 * @property int $due_day
 * @property bool $is_required
 * @property string $status
 * @property int $sort_order
 * @property int|null $created_by
 */
#[Fillable(['checklist_id', 'task_name', 'description', 'category', 'assigned_to_role', 'due_day', 'is_required', 'status', 'sort_order', 'created_by'])]
class ChecklistItem extends Model
{
    use HasCreator;

    public const MODULE = 'checklist-items';

    public const CATEGORIES = ['Documentation', 'IT Setup', 'Training', 'HR', 'Facilities', 'Other'];

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checklist_id' => 'integer',
            'due_day' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OnboardingChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(OnboardingChecklist::class, 'checklist_id');
    }
}
