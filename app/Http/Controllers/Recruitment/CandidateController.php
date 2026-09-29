<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\CandidateOnboarding;
use App\Models\CandidateSource;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Candidates arrive by applying to a job posting, so the demo has no create
 * permission: the list supports review, edit (incl. pipeline status) and delete.
 */
class CandidateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Candidate::query()
            ->visibleTo($request->user())
            ->with(['job:id,title,job_code', 'source:id,name'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('job_id'), fn ($q, $id) => $q->where('job_id', $id))
            ->when($request->integer('source_id'), fn ($q, $id) => $q->where('source_id', $id));

        return Inertia::render('hr/recruitment/candidates/index', [
            'candidates' => TableQuery::paginate($query, $request, ['first_name', 'last_name', 'email', 'phone', 'current_company'], ['first_name', 'application_date', 'created_at']),
            'statusCounts' => $this->statusCounts($request),
            'jobPostings' => JobPosting::query()->orderBy('title')->get(['id', 'title', 'job_code']),
            'sources' => CandidateSource::query()->orderBy('name')->get(['id', 'name']),
            'filters' => TableQuery::filters($request, ['status', 'job_id', 'source_id']),
        ]);
    }

    /**
     * Every visible candidate in one board, one column per status; cards move through update().
     */
    public function kanban(Request $request): Response
    {
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        return Inertia::render('hr/recruitment/candidates/kanban', [
            'candidates' => Candidate::query()
                ->visibleTo($request->user())
                ->with(['job:id,title,job_code', 'source:id,name'])
                ->when($request->integer('job_id'), fn ($q, $id) => $q->where('job_id', $id))
                ->when($request->integer('source_id'), fn ($q, $id) => $q->where('source_id', $id))
                ->when($search, fn ($q) => $q->whereAny(['first_name', 'last_name', 'email', 'phone', 'current_company'], 'like', "%{$search}%"))
                ->latest('application_date')->latest('id')
                ->get(),
            'jobPostings' => JobPosting::query()->orderBy('title')->get(['id', 'title', 'job_code']),
            'sources' => CandidateSource::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'job_id', 'source_id']),
        ]);
    }

    /**
     * The candidate's profile and application, with every interview, assessment, offer and onboarding for them.
     */
    public function show(Request $request, Candidate $candidate): Response
    {
        abort_unless($candidate->isVisibleTo($request->user()), 404);

        $onboarding = CandidateOnboarding::query()->with(['checklist:id,name', 'tasks'])->where('candidate_id', $candidate->id)->first();
        $onboarding?->append(['status', 'progress']);

        return Inertia::render('hr/recruitment/candidates/show', [
            'candidate' => $candidate->load(['job:id,title,job_code', 'source:id,name']),
            'interviews' => Interview::query()->where('candidate_id', $candidate->id)
                ->with(['round:id,name', 'interviewType:id,name', 'interviewers:id,name,email,avatar_path'])
                ->orderBy('scheduled_date')->orderBy('scheduled_time')
                ->get(['id', 'candidate_id', 'round_id', 'interview_type_id', 'scheduled_date', 'scheduled_time', 'duration', 'status']),
            'assessments' => CandidateAssessment::query()->where('candidate_id', $candidate->id)->with('conductor:id,name')->latest('assessment_date')
                ->get(['id', 'candidate_id', 'assessment_name', 'assessment_date', 'score', 'max_score', 'pass_fail_status', 'conducted_by', 'comments']),
            'offers' => Offer::query()->where('candidate_id', $candidate->id)->latest('offer_date')
                ->get(['id', 'candidate_id', 'offer_date', 'salary', 'start_date', 'expiration_date', 'status']),
            'onboarding' => $onboarding,
        ]);
    }

    public function update(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($candidate->isVisibleTo($request->user()), 403);
        $candidate->update($this->validated($request));

        return $this->done(__('Candidate updated successfully.'));
    }

    public function destroy(Request $request, Candidate $candidate): RedirectResponse
    {
        abort_unless($candidate->isVisibleTo($request->user()), 403);
        $candidate->delete();

        return $this->done(__('Candidate deleted successfully.'));
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(Request $request): array
    {
        $counts = Candidate::query()->visibleTo($request->user())->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['all' => (int) $counts->sum()] + collect(Candidate::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'job_id' => ['required', 'integer', Rule::exists('job_postings', 'id')],
            'source_id' => ['nullable', 'integer', Rule::exists('candidate_sources', 'id')],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(Candidate::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'current_position' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'final_salary' => ['nullable', 'numeric', 'min:0'],
            'notice_period' => ['nullable', 'string', 'max:50'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'education' => ['nullable', 'string', 'max:2000'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'application_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Candidate::STATUSES)],
        ]);
    }
}
