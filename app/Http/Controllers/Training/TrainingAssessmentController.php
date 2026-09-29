<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingAssessment;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrainingAssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TrainingAssessment::query()
            ->visibleTo($request->user())
            ->with([
                'program:id,name',
                'results:id,employee_training_id,training_assessment_id,score,is_passed,assessment_date',
                'results.employeeTraining:id,employee_id', 'results.employeeTraining.employee:id,user_id,employee_id,gender',
                'results.employeeTraining.employee.user:id,name,avatar_path',
            ])
            ->withCount('results')
            ->when($request->integer('training_program_id'), fn ($q, $id) => $q->where('training_program_id', $id));

        $counts = TableQuery::countBy($query, 'type');
        $query->when(in_array($request->input('type'), TrainingAssessment::TYPES, true), fn ($q) => $q->where('type', $request->input('type')));

        return Inertia::render('hr/training-assessments/index', [
            'trainingAssessments' => TableQuery::paginate($query, $request, ['name', 'criteria'], ['name', 'type', 'passing_score', 'created_at']),
            'typeCounts' => ['all' => (int) $counts->sum()] + collect(TrainingAssessment::TYPES)->mapWithKeys(fn ($t) => [$t => (int) ($counts[$t] ?? 0)])->all(),
            'trainingPrograms' => TrainingProgram::query()->orderBy('name')->get(['id', 'name']),
            'filters' => TableQuery::filters($request, ['training_program_id', 'type']),
        ]);
    }

    /**
     * The assessment with each visible employee's result and the pass statistics.
     */
    public function show(Request $request, TrainingAssessment $trainingAssessment): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($trainingAssessment->isVisibleTo($user), 404);

        $results = $trainingAssessment->results()
            ->whereHas('employeeTraining', fn ($q) => $q->visibleTo($user))
            ->with(['employeeTraining:id,employee_id', 'employeeTraining.employee:id,user_id,employee_id,gender', 'employeeTraining.employee.user:id,name,email,avatar_path', 'assessor:id,name'])
            ->latest('assessment_date')
            ->get();

        return Inertia::render('hr/training-assessments/show', [
            'trainingAssessment' => $trainingAssessment->load('program:id,name'),
            'results' => $results,
            'statistics' => [
                'total' => $results->count(),
                'passed' => $results->where('is_passed', true)->count(),
                'averageScore' => $results->isEmpty() ? null : round((float) $results->avg('score'), 2),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TrainingAssessment::create($this->validated($request));

        return $this->done(__('Training assessment created successfully.'));
    }

    public function update(Request $request, TrainingAssessment $trainingAssessment): RedirectResponse
    {
        abort_unless($trainingAssessment->isVisibleTo($request->user()), 403);
        $trainingAssessment->update($this->validated($request));

        return $this->done(__('Training assessment updated successfully.'));
    }

    public function destroy(Request $request, TrainingAssessment $trainingAssessment): RedirectResponse
    {
        abort_unless($trainingAssessment->isVisibleTo($request->user()), 403);
        $trainingAssessment->delete();

        return $this->done(__('Training assessment deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'training_program_id' => ['required', 'integer', Rule::exists(TrainingProgram::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(TrainingAssessment::TYPES)],
            'passing_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'criteria' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
