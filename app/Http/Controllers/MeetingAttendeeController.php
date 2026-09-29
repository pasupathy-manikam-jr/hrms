<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingAttendeeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = MeetingAttendee::query()
            ->visibleTo($user)
            ->with(['meeting:id,title,meeting_date,start_time,end_time,status', 'user:id,name,email,avatar_path'])
            ->when(trim($request->string('search')->toString()), function (Builder $q, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn (Builder $q) => $q
                    ->whereHas('user', fn (Builder $u) => $u->whereLike('name', $like)->orWhereLike('email', $like))
                    ->orWhereHas('meeting', fn (Builder $m) => $m->whereLike('title', $like)));
            })
            ->when($request->input('rsvp_status'), fn ($q, $status) => $q->where('rsvp_status', $status))
            ->when($request->input('attendance_status'), fn ($q, $status) => $q->where('attendance_status', $status))
            ->when($request->input('meeting_id'), fn ($q, $meeting) => $q->where('meeting_id', $meeting));

        // Search is applied above (it spans the user and meeting), so TableQuery gets no searchable columns.
        return Inertia::render('meetings/meeting-attendees/index', [
            'meetingAttendees' => TableQuery::paginate($query, $request, [], ['rsvp_status', 'attendance_status', 'type', 'created_at']),
            'filters' => TableQuery::filters($request, ['rsvp_status', 'attendance_status', 'meeting_id']),
            'meetings' => Meeting::query()->visibleTo($user)->latest('meeting_date')->get(['id', 'title', 'meeting_date']),
            'users' => $user->can('create-meeting-attendees') ? User::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'meeting_id' => ['required', 'integer', Rule::exists(Meeting::class, 'id')],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')],
            'type' => ['required', Rule::in(MeetingAttendee::TYPES)],
        ]);

        $meeting = Meeting::query()->visibleTo($request->user())->findOrFail($request->integer('meeting_id'));
        $meeting->attendees()->syncWithoutDetaching(array_fill_keys($data['user_ids'], ['type' => $data['type']]));

        return $this->done(__('Attendees added successfully.'));
    }

    /**
     * Managers update any field; everyone else can only RSVP to their own invitation.
     */
    public function update(Request $request, MeetingAttendee $meetingAttendee): RedirectResponse
    {
        $user = $request->user();
        $this->ensureVisible($request, $meetingAttendee);

        $data = $request->validate([
            'type' => ['sometimes', Rule::in(MeetingAttendee::TYPES)],
            'rsvp_status' => ['required', Rule::in(MeetingAttendee::RSVP_STATUSES)],
            'attendance_status' => ['sometimes', Rule::in(MeetingAttendee::ATTENDANCE_STATUSES)],
            'decline_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $user->can('manage-any-meeting-attendees')) {
            $data = Arr::only($data, ['rsvp_status', 'decline_reason']);
        }

        if ($data['rsvp_status'] !== $meetingAttendee->rsvp_status) {
            $data['rsvp_date'] = $data['rsvp_status'] === 'Pending' ? null : today();
        }
        $data['decline_reason'] = $data['rsvp_status'] === 'Declined' ? ($data['decline_reason'] ?? null) : null;

        $meetingAttendee->update($data);

        return $this->done(__('Attendee updated successfully.'));
    }

    public function destroy(Request $request, MeetingAttendee $meetingAttendee): RedirectResponse
    {
        $this->ensureVisible($request, $meetingAttendee);
        $meetingAttendee->delete();

        return $this->done(__('Attendee removed successfully.'));
    }

    private function ensureVisible(Request $request, MeetingAttendee $meetingAttendee): void
    {
        abort_unless(MeetingAttendee::query()->visibleTo($request->user())->whereKey($meetingAttendee->id)->exists(), 404);
    }
}
