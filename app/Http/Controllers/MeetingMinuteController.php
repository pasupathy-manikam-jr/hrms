<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingMinuteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = MeetingMinute::query()
            ->visibleTo($user)
            ->with(['meeting:id,title,meeting_date', 'recorder:id,name,email,avatar_path'])
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->input('meeting_id'), fn ($q, $meeting) => $q->where('meeting_id', $meeting))
            ->when($request->input('recorded_by'), fn ($q, $recorder) => $q->where('recorded_by', $recorder));

        return Inertia::render('meetings/meeting-minutes/index', [
            'meetingMinutes' => TableQuery::paginate($query, $request, ['topic', 'content'], ['topic', 'type', 'recorded_at', 'created_at'], 'recorded_at'),
            'filters' => TableQuery::filters($request, ['type', 'meeting_id', 'recorded_by']),
            'meetings' => $this->meetings($user)->latest('meeting_date')->get(['id', 'title', 'meeting_date']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MeetingMinute::create([...$this->validated($request), 'recorded_by' => $request->user()->id]);

        return $this->done(__('Meeting minute created successfully.'));
    }

    public function update(Request $request, MeetingMinute $meetingMinute): RedirectResponse
    {
        $this->ensureVisible($request, $meetingMinute);
        $meetingMinute->update($this->validated($request));

        return $this->done(__('Meeting minute updated successfully.'));
    }

    public function destroy(Request $request, MeetingMinute $meetingMinute): RedirectResponse
    {
        $this->ensureVisible($request, $meetingMinute);
        $meetingMinute->delete();

        return $this->done(__('Meeting minute deleted successfully.'));
    }

    /**
     * Meetings whose minutes the user may see and record.
     *
     * @return Builder<Meeting>
     */
    private function meetings(User $user): Builder
    {
        return $user->can('manage-any-meeting-minutes') ? Meeting::query() : Meeting::query()->visibleTo($user);
    }

    private function ensureVisible(Request $request, MeetingMinute $meetingMinute): void
    {
        abort_unless(MeetingMinute::query()->visibleTo($request->user())->whereKey($meetingMinute->id)->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'meeting_id' => ['required', 'integer', Rule::exists(Meeting::class, 'id')],
            'topic' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in(MeetingMinute::TYPES)],
            'recorded_at' => ['nullable', 'date'],
        ]);

        abort_unless($this->meetings($request->user())->whereKey($data['meeting_id'])->exists(), 404);
        $data['recorded_at'] ??= now();

        return $data;
    }
}
