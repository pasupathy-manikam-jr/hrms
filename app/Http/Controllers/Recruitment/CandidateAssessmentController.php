<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CandidateAssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CandidateAssessment::query()
            ->visibleTo($request->user())
            ->with(['candidate:id,first_name,last_name,email', 'conductor:id,name,email,avatar_path'])
            ->when($request->input('candidate_id'), fn ($q, $id) => $q->where('candidate_id', $id));

        $counts = TableQuery::countBy($query, 'pass_fail_status');
        $query->when(in_array($request->input('status'), CandidateAssessment::STATUSES, true), fn ($q) => $q->where('pass_fail_status', $request->input('status')));

        return Inertia::render('hr/recruitment/candidate-assessments/index', [
            'assessments' => TableQuery::paginate($query, $request, ['assessment_name', 'comments'], ['assessment_name', 'assessment_date', 'score', 'created_at'], 'assessment_date'),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(['Pending', 'Pass', 'Fail'])->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'candidates' => Candidate::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                ->map(fn (Candidate $c) => ['id' => $c->id, 'name' => trim("{$c->first_name} {$c->last_name}")]),
            'employees' => User::query()->orderBy('name')->get(['id', 'name']),
            'passMark' => CandidateAssessment::PASS_MARK,
            'filters' => TableQuery::filters($request, ['status', 'candidate_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CandidateAssessment::create($this->validated($request));

        return $this->done(__('Candidate assessment created successfully.'));
    }

    public function update(Request $request, CandidateAssessment $candidateAssessment): RedirectResponse
    {
        abort_unless($candidateAssessment->isVisibleTo($request->user()), 403);
        $candidateAssessment->update($this->validated($request));

        return $this->done(__('Candidate assessment updated successfully.'));
    }

    public function destroy(Request $request, CandidateAssessment $candidateAssessment): RedirectResponse
    {
        abort_unless($candidateAssessment->isVisibleTo($request->user()), 403);
        $candidateAssessment->delete();

        return $this->done(__('Candidate assessment deleted successfully.'));
    }

    /**
     * pass_fail_status is not accepted here; the model derives it from score / max_score.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'candidate_id' => ['required', Rule::exists('candidates', 'id')],
            'assessment_name' => ['required', 'string', 'max:255'],
            'assessment_date' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'score' => ['nullable', 'numeric', 'min:0', 'lte:max_score'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'conducted_by' => ['nullable', Rule::exists('users', 'id')],
        ]);
    }
}
