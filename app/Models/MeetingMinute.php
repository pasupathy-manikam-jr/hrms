<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meeting_id', 'topic', 'content', 'type', 'recorded_by', 'recorded_at'])]
class MeetingMinute extends Model
{
    public const TYPES = ['Note', 'Discussion', 'Decision', 'Action Item'];

    /**
     * Minutes the user may see: all with manage-any-meeting-minutes, otherwise those of meetings visible to them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-meeting-minutes')) {
            $query->whereHas('meeting', fn (Builder $meeting) => $meeting->visibleTo($user));
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'recorded_by' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
