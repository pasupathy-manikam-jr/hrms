<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\TrainingType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrainingTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TrainingType::query()
            ->visibleTo($request->user())
            ->with(['branch:id,name', 'departments:id,name'])
            ->withCount('trainingPrograms')
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->whereHas('departments', fn ($d) => $d->whereKey($id)));

        return Inertia::render('hr/training-types/index', [
            'trainingTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'branch_id']),
            'filters' => TableQuery::filters($request, ['branch_id', 'department_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $departments] = $this->validated($request);

        TrainingType::create($data)->departments()->sync($departments);

        return $this->done(__('Training type created successfully.'));
    }

    public function update(Request $request, TrainingType $trainingType): RedirectResponse
    {
        abort_unless($trainingType->isVisibleTo($request->user()), 403);
        [$data, $departments] = $this->validated($request);

        $trainingType->update($data);
        $trainingType->departments()->sync($departments);

        return $this->done(__('Training type updated successfully.'));
    }

    /**
     * The demo's "Assign Departments" action: replace the type's departments, keeping them within its branch.
     */
    public function assignDepartments(Request $request, TrainingType $trainingType): RedirectResponse
    {
        abort_unless($trainingType->isVisibleTo($request->user()), 403);
        $data = $request->validate([
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists(Department::class, 'id')
                ->when($trainingType->branch_id, fn ($rule, $branchId) => $rule->where('branch_id', $branchId))],
        ]);

        $trainingType->departments()->sync(array_map('intval', $data['department_ids']));

        return $this->done(__('Departments assigned successfully.'));
    }

    public function destroy(Request $request, TrainingType $trainingType): RedirectResponse
    {
        abort_unless($trainingType->isVisibleTo($request->user()), 403);
        $trainingType->delete();

        return $this->done(__('Training type deleted successfully.'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')],
            'department_ids' => ['array'],
            // Departments must belong to the chosen branch.
            'department_ids.*' => ['integer', 'distinct', Rule::exists(Department::class, 'id')->where('branch_id', $request->integer('branch_id'))],
        ]);

        $departments = array_values(array_map('intval', $data['department_ids'] ?? []));
        unset($data['department_ids']);

        return [$data, $departments];
    }
}
