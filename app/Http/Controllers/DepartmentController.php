<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Department::query()
            ->with('branch:id,name')
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('hr/departments/index', [
            'departments' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'statusCounts' => $this->statusCounts(),
            'filters' => TableQuery::filters($request, ['branch_id', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create($this->validated($request));

        return $this->done(__('Department created successfully.'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request));

        return $this->done(__('Department updated successfully.'));
    }

    public function toggleStatus(Department $department): RedirectResponse
    {
        $department->update(['status' => $department->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Department status updated.'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return $this->done(__('Department deleted successfully.'));
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $counts = Department::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['all' => (int) $counts->sum()] + collect(Department::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(Department::STATUSES)],
        ]);
    }
}
