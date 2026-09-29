<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingRoomController extends Controller
{
    public function index(Request $request): Response
    {
        $query = MeetingRoom::query()->withCount('meetings')
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type));

        $counts = TableQuery::countBy($query, 'status');
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));
        $types = MeetingRoom::query()->toBase()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return Inertia::render('meetings/meeting-rooms/index', [
            'statusCounts' => ['all' => (int) $counts->sum(), 'active' => (int) ($counts['active'] ?? 0), 'inactive' => (int) ($counts['inactive'] ?? 0)],
            'stats' => [
                'total' => (int) $types->sum(),
                'active' => MeetingRoom::query()->where('status', 'active')->count(),
                'physical' => (int) ($types['Physical'] ?? 0),
                'virtual' => (int) ($types['Virtual'] ?? 0),
            ],
            'meetingRooms' => TableQuery::paginate($query, $request, ['name', 'location', 'description'], ['name', 'capacity', 'created_at']),
            'filters' => TableQuery::filters($request, ['type', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MeetingRoom::create($this->validated($request));

        return $this->done(__('Meeting room created successfully.'));
    }

    public function update(Request $request, MeetingRoom $meetingRoom): RedirectResponse
    {
        $meetingRoom->update($this->validated($request));

        return $this->done(__('Meeting room updated successfully.'));
    }

    public function destroy(MeetingRoom $meetingRoom): RedirectResponse
    {
        $meetingRoom->delete();

        return $this->done(__('Meeting room deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(MeetingRoom::TYPES)],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:100'],
            'booking_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(MeetingRoom::STATUSES)],
        ]);
    }
}
