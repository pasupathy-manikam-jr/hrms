<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateOnboarding;
use App\Models\CandidateOnboardingTask;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CandidateOnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $query = CandidateOnboarding::query()
            ->visibleTo($request->user())
            ->with(['candidate:id,first_name,last_name,email', 'checklist:id,name', 'buddy:id,user_id,employee_id,gender', 'buddy.user:id,name,email,avatar_path', 'tasks'])
            ->when($request->input('candidate_id'), fn ($q, $id) => $q->where('candidate_id', $id))
            ->when($search, fn ($q) => $q->whereHas('candidate', fn ($c) => $c
                ->where('first_name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('last_name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($search, '%_\\').'%')));

        $statusCounts = ['all' => (clone $query)->count()]
            + collect(CandidateOnboarding::STATUSES)->mapWithKeys(fn ($s) => [$s => (clone $query)->withStatus($s)->count()])->all();
        $query->when(in_array($request->input('status'), CandidateOnboarding::STATUSES, true), fn ($q) => $q->withStatus($request->input('status')));

        $onboardings = TableQuery::paginate($query, $request, [], ['start_date', 'created_at'], 'start_date');
        foreach ($onboardings->items() as $onboarding) {
            $onboarding->append(['status', 'progress']);
        }

        return Inertia::render('hr/recruitment/candidate-onboarding/index', [
            'candidateOnboarding' => $onboardings,
            'statusCounts' => $statusCounts,
            'candidates' => Candidate::query()->where('status', 'Hired')->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                ->map(fn (Candidate $c) => ['id' => $c->id, 'name' => trim("{$c->first_name} {$c->last_name}")]),
            'checklists' => OnboardingChecklist::query()->where('status', 'active')->withCount('items')->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']),
            'buddyEmployees' => Employee::query()->where('employee_status', '!=', 'terminated')->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id]),
            'filters' => TableQuery::filters($request, ['status', 'candidate_id']),
        ]);
    }

    public function show(Request $request, CandidateOnboarding $candidateOnboarding): Response
    {
        abort_unless($candidateOnboarding->isVisibleTo($request->user()), 404);

        return Inertia::render('hr/recruitment/candidate-onboarding/show', [
            'onboarding' => $candidateOnboarding
                ->load(['candidate:id,first_name,last_name,email,phone,gender,job_id', 'candidate.job:id,title', 'checklist:id,name', 'buddy:id,user_id,employee_id,gender', 'buddy.user:id,name,email,avatar_path', 'tasks'])
                ->append(['status', 'progress']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CandidateOnboarding::start($request->validate([
            'candidate_id' => [
                'required',
                Rule::exists('candidates', 'id')->where('status', 'Hired'),
                Rule::unique('candidate_onboardings', 'candidate_id'),
            ],
            'checklist_id' => ['required', Rule::exists('onboarding_checklists', 'id')],
            ...$this->scheduleRules(),
        ], [
            'candidate_id.exists' => __('Only hired candidates can be onboarded.'),
            'candidate_id.unique' => __('This candidate is already being onboarded.'),
        ]));

        return $this->done(__('Onboarding started successfully.'));
    }

    /**
     * The checklist is fixed once onboarding starts (its tasks are already copied); the start date and buddy can change.
     */
    public function update(Request $request, CandidateOnboarding $candidateOnboarding): RedirectResponse
    {
        abort_unless($candidateOnboarding->isVisibleTo($request->user()), 403);
        $candidateOnboarding->update($request->validate($this->scheduleRules()));

        return $this->done(__('Onboarding updated successfully.'));
    }

    public function destroy(Request $request, CandidateOnboarding $candidateOnboarding): RedirectResponse
    {
        abort_unless($candidateOnboarding->isVisibleTo($request->user()), 403);
        $candidateOnboarding->delete();

        return $this->done(__('Onboarding deleted successfully.'));
    }

    public function updateTask(Request $request, CandidateOnboarding $candidateOnboarding, CandidateOnboardingTask $task): RedirectResponse
    {
        abort_unless($candidateOnboarding->isVisibleTo($request->user()), 403);
        $completed = $request->validate(['completed' => ['required', 'boolean']])['completed'];

        $task->update([
            'status' => $completed ? 'completed' : 'pending',
            'completed_at' => $completed ? ($task->completed_at ?? now()) : null,
        ]);

        return $this->done($completed ? __('Task marked as done.') : __('Task reopened.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleRules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'buddy_employee_id' => ['nullable', Rule::exists('employees', 'id')],
        ];
    }
}
