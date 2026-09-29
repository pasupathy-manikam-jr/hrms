<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Shift::query()
            ->when(in_array($request->input('status'), Shift::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')))
            ->when(in_array($request->input('shift_type'), ['day', 'night'], true), fn ($q) => $q->where('is_night_shift', $request->input('shift_type') === 'night'));

        return Inertia::render('hr/shifts/index', [
            'shifts' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'start_time', 'end_time', 'created_at']),
            'typeCounts' => [
                'night' => Shift::query()->where('is_night_shift', true)->count(),
                'day' => Shift::query()->where('is_night_shift', false)->count(),
            ],
            'statusCounts' => [
                'all' => Shift::query()->count(),
                'active' => Shift::query()->where('status', 'active')->count(),
                'inactive' => Shift::query()->where('status', 'inactive')->count(),
            ],
            'filters' => TableQuery::filters($request, ['status', 'shift_type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Shift::create($this->validated($request));

        return $this->done(__('Shift created successfully.'));
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $shift->update($this->validated($request));

        return $this->done(__('Shift updated successfully.'));
    }

    public function toggleStatus(Shift $shift): RedirectResponse
    {
        $shift->update(['status' => $shift->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Shift status updated.'));
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return $this->done(__('Shift deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_duration' => ['required', 'integer', 'min:0', 'max:1440'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_end_time' => ['nullable', 'date_format:H:i'],
            'grace_period' => ['required', 'integer', 'min:0', 'max:1440'],
            'is_night_shift' => ['boolean'],
            'status' => ['required', Rule::in(Shift::STATUSES)],
        ]);
    }
}
