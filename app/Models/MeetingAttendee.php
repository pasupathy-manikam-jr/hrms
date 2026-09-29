<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the meeting_attendees pivot, listed and managed on its own page.
 *
 * @property int $user_id
 */
#[Fillable(['meeting_id', 'user_id', 'type', 'rsvp_status', 'attendance_status', 'rsvp_date', 'decline_reason'])]
class MeetingAttendee extends Model
{
    public const TYPES = ['Required', 'Optional'];

    public const RSVP_STATUSES = ['Pending', 'Accepted', 'Declined', 'Tentative'];

    public const ATTENDANCE_STATUSES = ['Not Attended', 'Present', 'Late'];

    /**
     * Rows the user may see: all with manage-any-meeting-attendees, otherwise only their own invitations.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-meeting-attendees')) {
            $query->where('user_id', $user->id);
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'user_id' => 'integer',
            'rsvp_date' => 'date:Y-m-d',
        ];
    }
}
