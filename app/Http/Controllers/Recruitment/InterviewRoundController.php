<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\InterviewRound;
use App\Models\JobPosting;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InterviewRoundController extends Controller
{
    public function index(Request $request): Response
    {
        $query = InterviewRound::query()
            ->visibleTo($request->user())
            ->with('job:id,title,job_code')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('job_id'), fn ($q, $id) => $q->where('job_id', $id));

        $counts = InterviewRound::query()->visibleTo($request->user())->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('hr/recruitment/interview-rounds/index', [
            'interviewRounds' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'sequence_number', 'created_at']),
            'jobPostings' => JobPosting::query()->orderBy('title')->get(['id', 'title', 'job_code']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(InterviewRound::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'job_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        InterviewRound::create($this->validated($request));

        return $this->done(__('Interview round created successfully.'));
    }

    public function update(Request $request, InterviewRound $interviewRound): RedirectResponse
    {
        abort_unless($interviewRound->isVisibleTo($request->user()), 403);
        $interviewRound->update($this->validated($request, $interviewRound));

        return $this->done(__('Interview round updated successfully.'));
    }

    public function toggleStatus(Request $request, InterviewRound $interviewRound): RedirectResponse
    {
        abort_unless($interviewRound->isVisibleTo($request->user()), 403);
        $interviewRound->update(['status' => $interviewRound->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Interview round status updated.'));
    }

    public function destroy(Request $request, InterviewRound $interviewRound): RedirectResponse
    {
        abort_unless($interviewRound->isVisibleTo($request->user()), 403);
        $interviewRound->delete();

        return $this->done(__('Interview round deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?InterviewRound $round = null): array
    {
        return $request->validate([
            'job_id' => ['required', 'integer', Rule::exists('job_postings', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'sequence_number' => [
                'required', 'integer', 'min:1', 'max:100',
                Rule::unique('interview_rounds')->where('job_id', $request->integer('job_id'))->ignore($round),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(InterviewRound::STATUSES)],
        ]);
    }
}
