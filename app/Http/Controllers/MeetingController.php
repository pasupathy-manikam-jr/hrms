<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\MeetingRoom;
use App\Models\MeetingType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    /**
     * The demo's day agenda: every meeting the user may see is sent once, and picking a day or month
     * happens in the browser (no query string).
     */
    public function index(Request $request): Response
    {
        // ponytail: sends all visible meetings; load one month at a time if meetings run into the thousands.
        return Inertia::render('meetings/meetings/index', [
            'meetings' => Meeting::query()
                ->visibleTo($request->user())
                ->with(['type:id,name,color', 'room:id,name,type', 'organizer:id,name,email,avatar_path', 'attendees:id,name,avatar_path'])
                ->orderBy('meeting_date')->orderBy('start_time')->orderBy('id')
                ->get(),
            'meetingTypes' => MeetingType::query()->orderBy('name')->get(['id', 'name', 'default_duration']),
            'meetingRooms' => MeetingRoom::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'type']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function changeStatus(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureVisible($request, $meeting);
        $meeting->update($request->validate(['status' => ['required', Rule::in(Meeting::STATUSES)]]));

        return $this->done(__('Meeting status updated.'));
    }

    public function show(Request $request, Meeting $meeting): Response
    {
        $this->ensureVisible($request, $meeting);
        $person = 'id,name,email,avatar_path';

        return Inertia::render('meetings/meetings/show', [
            'meeting' => $meeting->load([
                'type:id,name,color', 'room:id,name,type,location,capacity', "organizer:{$person}",
                'attendees' => fn ($q) => $q->select(['users.id', 'users.name', 'users.email', 'users.avatar_path'])->orderBy('users.name'),
            ]),
            'minutes' => MeetingMinute::query()->where('meeting_id', $meeting->id)
                ->with("recorder:{$person}")->latest('recorded_at')
                ->get(['id', 'meeting_id', 'topic', 'content', 'type', 'recorded_by', 'recorded_at']),
            'actionItems' => $meeting->actionItems()->with("assignee:{$person}")->orderBy('due_date')
                ->get(['id', 'meeting_id', 'title', 'description', 'assigned_to', 'due_date', 'priority', 'status', 'progress_percentage']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $attendees] = $this->validated($request);

        Meeting::create($data)->attendees()->sync($attendees);

        return $this->done(__('Meeting created successfully.'));
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureVisible($request, $meeting);
        [$data, $attendees] = $this->validated($request);

        $meeting->update($data);
        $meeting->attendees()->sync($attendees);

        return $this->done(__('Meeting updated successfully.'));
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->ensureVisible($request, $meeting);
        $meeting->delete();

        return $this->done(__('Meeting deleted successfully.'));
    }

    private function ensureVisible(Request $request, Meeting $meeting): void
    {
        abort_unless(Meeting::query()->visibleTo($request->user())->whereKey($meeting->id)->exists(), 404);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type_id' => ['required', 'integer', Rule::exists(MeetingType::class, 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists(MeetingRoom::class, 'id')],
            'meeting_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s', 'after:start_time'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(Meeting::STATUSES)],
            'recurrence' => ['required', Rule::in(Meeting::RECURRENCES)],
            'recurrence_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:meeting_date'],
            'organizer_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'attendee_ids' => ['array'],
            'attendee_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')],
        ]);

        $attendees = array_map('intval', $data['attendee_ids'] ?? []);
        unset($data['attendee_ids']);

        $data['duration'] = (int) Carbon::parse($data['start_time'])->diffInMinutes(Carbon::parse($data['end_time']));
        if ($data['recurrence'] === 'None') {
            $data['recurrence_end_date'] = null;
        }

        return [$data, $attendees];
    }
}
