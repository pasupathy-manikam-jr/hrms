<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeGoal;
use App\Models\GoalType;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeGoalController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = EmployeeGoal::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'goalType:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('goal_type_id'), fn ($q, $id) => $q->where('goal_type_id', $id))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('target', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), EmployeeGoal::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/performance/employee-goals/index', [
            // Search is applied above so the status counts match it; TableQuery gets no searchable columns.
            'goals' => TableQuery::paginate($query, $request, [], ['title', 'start_date', 'end_date', 'progress', 'status', 'created_at']),
            'employees' => $user->can('manage-any-employee-goals') ? $this->employees() : [],
            'goalTypes' => GoalType::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'statuses' => EmployeeGoal::STATUSES,
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(EmployeeGoal::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'goal_type_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EmployeeGoal::create([...$this->validated($request), 'created_by' => $request->user()?->id]);

        return $this->done(__('Employee goal created successfully.'));
    }

    public function update(Request $request, EmployeeGoal $employeeGoal): RedirectResponse
    {
        $this->authorizeRecord($request, $employeeGoal);
        $employeeGoal->update($this->validated($request));

        return $this->done(__('Employee goal updated successfully.'));
    }

    /**
     * The demo's "update progress" action. Reaching 100% completes the goal; any progress starts it.
     */
    public function progress(Request $request, EmployeeGoal $employeeGoal): RedirectResponse
    {
        $this->authorizeRecord($request, $employeeGoal);
        $data = $request->validate(['progress' => ['required', 'integer', 'between:0,100']]);

        $employeeGoal->update([
            'progress' => $data['progress'],
            'status' => match (true) {
                $data['progress'] === 100 => 'completed',
                $data['progress'] > 0 => 'in_progress',
                default => 'not_started',
            },
        ]);

        return $this->done(__('Goal progress updated.'));
    }

    public function destroy(Request $request, EmployeeGoal $employeeGoal): RedirectResponse
    {
        $this->authorizeRecord($request, $employeeGoal);
        $employeeGoal->delete();

        return $this->done(__('Employee goal deleted successfully.'));
    }

    private function authorizeRecord(Request $request, EmployeeGoal $employeeGoal): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(EmployeeGoal::query()->visibleTo($user)->whereKey($employeeGoal->id)->exists(), 404);
    }

    /**
     * @return array<int, array{id: int, name: string, employee_id: string}>
     */
    private function employees(): array
    {
        return Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
            ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id])
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'goal_type_id' => ['required', 'integer', Rule::exists('goal_types', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'target' => ['nullable', 'string', 'max:255'],
            'progress' => ['required', 'integer', 'between:0,100'],
            'status' => ['required', Rule::in(EmployeeGoal::STATUSES)],
        ]);
    }
}
