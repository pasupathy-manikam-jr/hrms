<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Designation::query()
            ->with('department:id,name,branch_id', 'department.branch:id,name')
            ->when($request->integer('department'), fn ($q, $id) => $q->where('department_id', $id));

        return Inertia::render('hr/designations/index', [
            'designations' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'departments' => Department::query()->with('branch:id,name')->orderBy('name')->get(['id', 'name', 'branch_id']),
            'filters' => TableQuery::filters($request, ['department']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Designation::create($this->validated($request));

        return $this->done(__('Designation created successfully.'));
    }

    public function update(Request $request, Designation $designation): RedirectResponse
    {
        $designation->update($this->validated($request));

        return $this->done(__('Designation updated successfully.'));
    }

    public function toggleStatus(Designation $designation): RedirectResponse
    {
        $designation->update(['status' => $designation->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Designation status updated.'));
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $designation->delete();

        return $this->done(__('Designation deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(Designation::STATUSES)],
        ]);
    }
}
