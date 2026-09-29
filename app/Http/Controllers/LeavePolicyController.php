<?php

namespace App\Http\Controllers;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeavePolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LeavePolicy::query()
            ->with('leaveType:id,name,color')
            ->when($request->integer('leave_type_id'), fn ($q, $id) => $q->where('leave_type_id', $id));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), LeavePolicy::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/leave-policies/index', [
            'leavePolicies' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'accrual_rate', 'max_days_per_application', 'created_at']),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(['id', 'name', 'color']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(LeavePolicy::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['leave_type_id', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LeavePolicy::create($this->validated($request));

        return $this->done(__('Leave policy created successfully.'));
    }

    public function update(Request $request, LeavePolicy $leavePolicy): RedirectResponse
    {
        $leavePolicy->update($this->validated($request));

        return $this->done(__('Leave policy updated successfully.'));
    }

    public function toggleStatus(LeavePolicy $leavePolicy): RedirectResponse
    {
        $leavePolicy->update(['status' => $leavePolicy->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Leave policy status updated.'));
    }

    public function destroy(LeavePolicy $leavePolicy): RedirectResponse
    {
        $leavePolicy->delete();

        return $this->done(__('Leave policy deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')],
            'accrual_type' => ['required', Rule::in(LeavePolicy::ACCRUAL_TYPES)],
            'accrual_rate' => ['required', 'numeric', 'min:0', 'max:366'],
            'carry_forward_limit' => ['required', 'integer', 'min:0', 'max:366'],
            'min_days_per_application' => ['required', 'integer', 'min:1', 'max:366'],
            'max_days_per_application' => ['required', 'integer', 'gte:min_days_per_application', 'max:366'],
            'requires_approval' => ['boolean'],
            'status' => ['required', Rule::in(LeavePolicy::STATUSES)],
        ]);
    }
}
