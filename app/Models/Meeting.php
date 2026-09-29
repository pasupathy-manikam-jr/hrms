<?php

namespace App\Models;

use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organizer_id
 * @property Carbon $meeting_date
 */
#[Fillable([
    'title', 'description', 'type_id', 'room_id', 'meeting_date', 'start_time', 'end_time', 'duration',
    'agenda', 'status', 'recurrence', 'recurrence_end_date', 'organizer_id',
])]
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    public const STATUSES = ['Scheduled', 'In Progress', 'Completed', 'Cancelled'];

    public const RECURRENCES = ['None', 'Daily', 'Weekly', 'Monthly'];

    /**
     * Meetings the user may see: all with manage-any-meetings, otherwise the ones they organise or attend.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-meetings')) {
            $query->where(fn (Builder $q) => $q
                ->where('organizer_id', $user->id)
                ->orWhereHas('attendees', fn (Builder $a) => $a->whereKey($user->id)));
        }
    }

    /**
     * @return BelongsTo<MeetingType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MeetingType::class, 'type_id');
    }

    /**
     * @return BelongsTo<MeetingRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_attendees')
            ->withPivot(['id', 'type', 'rsvp_status', 'attendance_status', 'rsvp_date', 'decline_reason'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ActionItem, $this>
     */
    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    protected function casts(): array
    {
        return [
            'type_id' => 'integer',
            'room_id' => 'integer',
            'organizer_id' => 'integer',
            'meeting_date' => 'date:Y-m-d',
            'recurrence_end_date' => 'date:Y-m-d',
            'duration' => 'integer',
        ];
    }
}
