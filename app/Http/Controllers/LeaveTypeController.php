<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LeaveType::query()
            ->when(in_array($request->input('status'), LeaveType::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/leave-types/index', [
            'leaveTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'max_days_per_year', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LeaveType::create($this->validated($request));

        return $this->done(__('Leave type created successfully.'));
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($this->validated($request));

        return $this->done(__('Leave type updated successfully.'));
    }

    public function toggleStatus(LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update(['status' => $leaveType->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Leave type status updated.'));
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $leaveType->delete();

        return $this->done(__('Leave type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_days_per_year' => ['required', 'integer', 'min:0', 'max:366'],
            'is_paid' => ['boolean'],
            'color' => ['required', 'hex_color'],
            'status' => ['required', Rule::in(LeaveType::STATUSES)],
        ]);
    }
}
