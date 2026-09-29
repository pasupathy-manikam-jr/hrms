<?php

namespace App\Models;

use Database\Factories\ActionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'meeting_id', 'title', 'description', 'assigned_to', 'due_date', 'priority', 'status',
    'progress_percentage', 'notes', 'completed_date',
])]
class ActionItem extends Model
{
    /** @use HasFactory<ActionItemFactory> */
    use HasFactory;

    public const STATUSES = ['Not Started', 'In Progress', 'Completed', 'Overdue'];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    /**
     * Items the user may see: all with manage-any-action-items, otherwise the ones assigned to them
     * or raised in meetings they organise.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-action-items')) {
            $query->where(fn (Builder $q) => $q
                ->where('assigned_to', $user->id)
                ->orWhereHas('meeting', fn (Builder $m) => $m->where('organizer_id', $user->id)));
        }
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'assigned_to' => 'integer',
            'due_date' => 'date:Y-m-d',
            'completed_date' => 'date:Y-m-d',
            'progress_percentage' => 'integer',
        ];
    }
}
