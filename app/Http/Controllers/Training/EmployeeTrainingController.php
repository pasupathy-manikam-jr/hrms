<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingAssessment;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeTrainingController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = EmployeeTraining::query()
            ->visibleTo($user)
            ->with([
                'employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path',
                'program:id,name,training_type_id', 'program.trainingType:id,name', 'session:id,name,start_date',
                'results:id,employee_training_id,training_assessment_id,score,is_passed,feedback,assessment_date',
                'results.assessment:id,name,passing_score',
            ])
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('training_program_id'), fn ($q, $id) => $q->where('training_program_id', $id))
            ->when($request->date('assigned_date_from'), fn ($q, $date) => $q->whereDate('assigned_date', '>=', $date))
            ->when($request->date('assigned_date_to'), fn ($q, $date) => $q->whereDate('assigned_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                ->orWhereHas('program', fn ($p) => $p->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), EmployeeTraining::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        $manageAny = $user->can('manage-any-employee-trainings');

        return Inertia::render('hr/employee-trainings/index', [
            // Search is applied above so the status counts match it; TableQuery gets no searchable columns.
            'employeeTrainings' => TableQuery::paginate($query, $request, [], ['status', 'assigned_date', 'completion_date', 'score', 'created_at'], 'assigned_date'),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(EmployeeTraining::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'employees' => $manageAny
                ? Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
                    ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id])
                : [],
            'trainingPrograms' => TrainingProgram::query()->orderBy('name')->get(['id', 'name', 'status', 'is_self_enrollment']),
            'trainingSessions' => TrainingSession::query()->orderBy('start_date')->get(['id', 'name', 'training_program_id', 'start_date']),
            'assessments' => TrainingAssessment::query()->orderBy('name')->get(['id', 'name', 'training_program_id', 'passing_score']),
            'filters' => TableQuery::filters($request, ['employee_id', 'training_program_id', 'status', 'assigned_date_from', 'assigned_date_to']),
        ]);
    }

    /**
     * Status totals, completion per program, and the latest completions and open assignments the user may see.
     */
    public function dashboard(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $visible = fn () => EmployeeTraining::query()->visibleTo($user);
        $with = ['employee:id,user_id,employee_id,gender', 'employee.user:id,name,avatar_path', 'program:id,name'];

        $counts = $visible()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $counts->sum();
        $completed = (int) ($counts['completed'] ?? 0);

        $programStats = $visible()->with('program:id,name')->get(['id', 'training_program_id', 'status'])
            ->groupBy('training_program_id')
            ->map(function ($rows) {
                $done = $rows->where('status', 'completed')->count();

                return ['name' => $rows->first()?->program->name, 'total' => $rows->count(), 'completed' => $done, 'completion_rate' => (int) round($done / $rows->count() * 100)];
            })
            ->sortByDesc('total')->take(5)->values();

        return Inertia::render('hr/employee-trainings/dashboard', [
            'statistics' => [
                'totalTrainings' => $total,
                'completedTrainings' => $completed,
                'inProgressTrainings' => (int) ($counts['in_progress'] ?? 0),
                'assignedTrainings' => (int) ($counts['assigned'] ?? 0),
                'failedTrainings' => (int) ($counts['failed'] ?? 0),
                'completionRate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            ],
            'programStats' => $programStats,
            'recentCompletions' => $visible()->with($with)->where('status', 'completed')->latest('completion_date')->latest('id')->limit(5)->get(),
            'upcomingTrainings' => $visible()->with($with)->where('status', 'assigned')->orderBy('assigned_date')->orderBy('id')->limit(5)->get(),
        ]);
    }

    public function show(Request $request, EmployeeTraining $employeeTraining): Response
    {
        $this->ensureVisible($request, $employeeTraining);

        return Inertia::render('hr/employee-trainings/show', [
            'employeeTraining' => $employeeTraining->load([
                'employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path',
                'program:id,name,training_type_id', 'program.trainingType:id,name', 'session:id,name,start_date,end_date',
                'results' => fn ($q) => $q->latest('assessment_date'),
                'results.assessment:id,name,type,passing_score', 'results.assessor:id,name',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EmployeeTraining::create([...$this->validated($request), 'assigned_by' => $request->user()?->id]);

        return $this->done(__('Training assigned successfully.'));
    }

    public function update(Request $request, EmployeeTraining $employeeTraining): RedirectResponse
    {
        $this->ensureVisible($request, $employeeTraining);
        $employeeTraining->update($this->validated($request, $employeeTraining));

        return $this->done(__('Employee training updated successfully.'));
    }

    public function destroy(Request $request, EmployeeTraining $employeeTraining): RedirectResponse
    {
        $this->ensureVisible($request, $employeeTraining);
        $employeeTraining->delete();

        return $this->done(__('Employee training deleted successfully.'));
    }

    /**
     * Assign one program to several employees. Without manage-any-employee-trainings this is
     * self-enrollment: only the user's own record, and only into active self-enrollment programs.
     */
    public function bulkAssign(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $manageAny = $user->can('manage-any-employee-trainings');

        if (! $manageAny) {
            $employee = $user->employee;
            abort_if($employee === null, 403);
            $request->merge(['employee_ids' => [$employee->id]]);
        }

        $data = $request->validate([
            'training_program_id' => ['required', 'integer', Rule::exists(TrainingProgram::class, 'id')
                ->when(! $manageAny, fn ($rule) => $rule->where('is_self_enrollment', true)->where('status', 'active'))],
            'training_session_id' => ['nullable', 'integer', Rule::exists(TrainingSession::class, 'id')->where('training_program_id', $request->integer('training_program_id'))],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists(Employee::class, 'id')],
            'assigned_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assigned = 0;
        foreach ($data['employee_ids'] as $employeeId) {
            $assigned += (int) EmployeeTraining::query()->firstOrCreate(
                ['employee_id' => $employeeId, 'training_program_id' => $data['training_program_id']],
                [
                    'training_session_id' => $data['training_session_id'] ?? null,
                    'status' => 'assigned',
                    'assigned_date' => $data['assigned_date'],
                    'notes' => $data['notes'] ?? null,
                    'assigned_by' => $user->id,
                ],
            )->wasRecentlyCreated;
        }

        return $this->done(trans_choice('{0} Everyone selected is already assigned.|{1} Training assigned to 1 employee.|[2,*] Training assigned to :count employees.', $assigned));
    }

    /**
     * Record (or re-record) an employee's result for one of the program's assessments.
     */
    public function recordAssessment(Request $request, EmployeeTraining $employeeTraining): RedirectResponse
    {
        $this->ensureVisible($request, $employeeTraining);
        // Nobody grades their own training.
        abort_if($employeeTraining->employee()->where('user_id', $request->user()?->id)->exists(), 403);

        $data = $request->validate([
            'training_assessment_id' => ['required', 'integer', Rule::exists(TrainingAssessment::class, 'id')->where('training_program_id', $employeeTraining->training_program_id)],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'assessment_date' => ['required', 'date_format:Y-m-d'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $assessment = TrainingAssessment::query()->whereKey($data['training_assessment_id'])->firstOrFail();

        $employeeTraining->results()->updateOrCreate(
            ['training_assessment_id' => $assessment->id],
            [
                'score' => $data['score'],
                'is_passed' => (float) $data['score'] >= (float) $assessment->passing_score,
                'assessment_date' => $data['assessment_date'],
                'feedback' => $data['feedback'] ?? null,
                'assessed_by' => $request->user()?->id,
            ],
        );

        return $this->done(__('Assessment result recorded successfully.'));
    }

    private function ensureVisible(Request $request, EmployeeTraining $employeeTraining): void
    {
        abort_unless(EmployeeTraining::query()->visibleTo($request->user())->whereKey($employeeTraining->id)->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EmployeeTraining $employeeTraining = null): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'training_program_id' => [
                'required', 'integer', Rule::exists(TrainingProgram::class, 'id'),
                Rule::unique(EmployeeTraining::class)->where('employee_id', $request->integer('employee_id'))->ignore($employeeTraining),
            ],
            'training_session_id' => ['nullable', 'integer', Rule::exists(TrainingSession::class, 'id')->where('training_program_id', $request->integer('training_program_id'))],
            'status' => ['required', Rule::in(EmployeeTraining::STATUSES)],
            'assigned_date' => ['required', 'date_format:Y-m-d'],
            'completion_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:assigned_date'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'certification' => ['boolean'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['training_program_id.unique' => __('This employee is already assigned to this program.')]);

        if (in_array($data['status'], ['completed', 'failed'], true)) {
            $data['completion_date'] ??= now()->toDateString();
        } else {
            $data['completion_date'] = null;
        }

        // A certificate is only issued for a completed training.
        $data['certification'] = $data['status'] === 'completed' && ($data['certification'] ?? false);

        return $data;
    }
}
