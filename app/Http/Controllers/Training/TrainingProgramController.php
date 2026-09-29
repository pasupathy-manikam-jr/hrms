<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTraining;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\TrainingType;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrainingProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TrainingProgram::query()
            ->visibleTo($request->user())
            ->with('trainingType:id,name')
            ->withCount(['sessions', 'employeeTrainings'])
            ->when($request->integer('training_type_id'), fn ($q, $id) => $q->where('training_type_id', $id))
            ->when($request->filled('is_mandatory'), fn ($q) => $q->where('is_mandatory', $request->boolean('is_mandatory')))
            ->when($request->filled('is_self_enrollment'), fn ($q) => $q->where('is_self_enrollment', $request->boolean('is_self_enrollment')));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), TrainingProgram::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/training-programs/index', [
            'trainingPrograms' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'status', 'duration', 'cost', 'capacity', 'created_at']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(TrainingProgram::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'trainingTypes' => TrainingType::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'filters' => TableQuery::filters($request, ['training_type_id', 'status', 'is_mandatory', 'is_self_enrollment']),
        ]);
    }

    /**
     * The program with the sessions, enrolments and assessment results the user may see.
     */
    public function show(Request $request, TrainingProgram $trainingProgram): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($trainingProgram->isVisibleTo($user), 404);

        return Inertia::render('hr/training-programs/show', [
            'trainingProgram' => $trainingProgram->load('trainingType:id,name'),
            'sessions' => TrainingSession::query()->visibleTo($user)->where('training_program_id', $trainingProgram->id)
                ->with(['trainers:id,user_id,employee_id,gender', 'trainers.user:id,name,email,avatar_path'])->orderBy('start_date')
                ->get(['id', 'training_program_id', 'name', 'start_date', 'end_date', 'location', 'location_type', 'status']),
            'enrollments' => EmployeeTraining::query()->visibleTo($user)->where('training_program_id', $trainingProgram->id)
                ->with(['employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path'])->latest('assigned_date')
                ->get(['id', 'employee_id', 'training_program_id', 'status', 'assigned_date', 'completion_date', 'score']),
            'assessments' => $trainingProgram->assessments()->orderBy('name')->get(['id', 'training_program_id', 'name', 'type', 'passing_score']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TrainingProgram::create($this->validated($request));

        return $this->done(__('Training program created successfully.'));
    }

    public function update(Request $request, TrainingProgram $trainingProgram): RedirectResponse
    {
        abort_unless($trainingProgram->isVisibleTo($request->user()), 403);
        $trainingProgram->update($this->validated($request));

        return $this->done(__('Training program updated successfully.'));
    }

    public function destroy(Request $request, TrainingProgram $trainingProgram): RedirectResponse
    {
        abort_unless($trainingProgram->isVisibleTo($request->user()), 403);
        $trainingProgram->delete();

        return $this->done(__('Training program deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'training_type_id' => ['required', 'integer', Rule::exists(TrainingType::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'cost' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(TrainingProgram::STATUSES)],
            'prerequisites' => ['nullable', 'string', 'max:255'],
            'is_mandatory' => ['boolean'],
            'is_self_enrollment' => ['boolean'],
        ]);
    }
}
