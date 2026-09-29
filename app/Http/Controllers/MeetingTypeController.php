<?php

namespace App\Http\Controllers;

use App\Models\MeetingType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = MeetingType::query()->withCount('meetings')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('meetings/meeting-types/index', [
            'meetingTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MeetingType::create($this->validated($request));

        return $this->done(__('Meeting type created successfully.'));
    }

    public function update(Request $request, MeetingType $meetingType): RedirectResponse
    {
        $meetingType->update($this->validated($request));

        return $this->done(__('Meeting type updated successfully.'));
    }

    /**
     * The lock action: switch the meeting type on or off.
     */
    public function toggleStatus(MeetingType $meetingType): RedirectResponse
    {
        $meetingType->update(['status' => $meetingType->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Meeting type status updated.'));
    }

    public function destroy(MeetingType $meetingType): RedirectResponse
    {
        $meetingType->delete();

        return $this->done(__('Meeting type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'hex_color'],
            'default_duration' => ['required', 'integer', 'min:5', 'max:1440'],
            'status' => ['required', Rule::in(MeetingType::STATUSES)],
        ]);
    }
}
