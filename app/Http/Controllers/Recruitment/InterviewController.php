<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\InterviewRound;
use App\Models\InterviewType;
use App\Models\User;
use App\Support\TableQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InterviewController extends Controller
{
    /** Candidate statuses that move to "Interview" once an interview is scheduled. */
    private const EARLY_STAGES = ['New', 'Screening'];

    public function index(Request $request): Response
    {
        $request->validate(['week_start' => ['nullable', 'date_format:Y-m-d'], 'selected_date' => ['nullable', 'date_format:Y-m-d']]);

        $weekStart = ($request->date('week_start') ?? $request->date('selected_date') ?? today())->toImmutable()->startOfWeek(CarbonInterface::MONDAY);
        $visible = fn () => Interview::query()->visibleTo($request->user());

        $query = Interview::query()
            ->visibleTo($request->user())
            ->with([
                'candidate:id,first_name,last_name,email,status,job_id',
                'job:id,title,job_code',
                'round:id,name,sequence_number',
                'interviewType:id,name',
                'interviewers:id,name,avatar_path',
            ])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('candidate_id'), fn ($q, $id) => $q->where('candidate_id', $id))
            ->when($request->input('search'), fn ($q, $search) => $q->whereHas('candidate', fn (Builder $c) => $c
                ->whereAny(['first_name', 'last_name', 'email'], 'like', '%'.addcslashes($search, '%_\\').'%')))
            // A picked day lists that day's interviews in time order, like the demo's day view.
            ->when($request->date('selected_date'), fn ($q, $date) => $q->whereDate('scheduled_date', $date)->orderBy('scheduled_time'));

        $weekCounts = $visible()->toBase()
            ->whereDate('scheduled_date', '>=', $weekStart)->whereDate('scheduled_date', '<=', $weekStart->addDays(6))
            ->selectRaw('scheduled_date, count(*) as total')->groupBy('scheduled_date')
            ->pluck('total', 'scheduled_date')
            ->mapWithKeys(fn ($total, $date) => [substr((string) $date, 0, 10) => (int) $total]);

        // The sidebar's next day with interviews still to happen.
        $nextDate = $visible()->where('status', 'Scheduled')->whereDate('scheduled_date', '>=', today())->min('scheduled_date');

        $counts = Interview::query()->visibleTo($request->user())->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('hr/recruitment/interviews/index', [
            // Search matches the candidate (whereHas above), so TableQuery gets no columns of its own.
            'interviews' => TableQuery::paginate($query, $request, [], ['scheduled_date', 'scheduled_time', 'duration', 'created_at'], 'scheduled_date'),
            'candidates' => Candidate::query()->visibleTo($request->user())->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'job_id']),
            'interviewRounds' => InterviewRound::query()->where('status', 'active')->orderBy('sequence_number')->get(['id', 'job_id', 'name', 'sequence_number']),
            'interviewTypes' => InterviewType::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'employees' => User::query()->orderBy('name')->get(['id', 'name']),
            'weekStart' => $weekStart->toDateString(),
            'weekCounts' => $weekCounts,
            'upcoming' => [
                'date' => $nextDate ? substr((string) $nextDate, 0, 10) : null,
                'interviews' => $nextDate
                    ? $visible()->with('candidate:id,first_name,last_name', 'job:id,title', 'round:id,name', 'interviewType:id,name')
                        ->where('status', 'Scheduled')->whereDate('scheduled_date', $nextDate)->orderBy('scheduled_time')->get()
                    : [],
            ],
            'pendingFeedback' => $visible()->where('feedback_submitted', false)->whereIn('status', ['Scheduled', 'Completed'])->count(),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Interview::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'candidate_id', 'week_start', 'selected_date']),
        ]);
    }

    /**
     * Every visible interview in one board, one column per status; cards move through updateStatus().
     */
    public function kanban(Request $request): Response
    {
        return Inertia::render('hr/recruitment/interviews/kanban', [
            'interviews' => Interview::query()
                ->visibleTo($request->user())
                ->with(['candidate:id,first_name,last_name,email', 'job:id,title', 'round:id,name', 'interviewType:id,name', 'interviewers:id,name,avatar_path'])
                ->when($request->integer('candidate_id'), fn ($q, $id) => $q->where('candidate_id', $id))
                ->when($request->input('search'), fn ($q, $search) => $q->whereHas('candidate', fn (Builder $c) => $c
                    ->whereAny(['first_name', 'last_name', 'email'], 'like', '%'.addcslashes($search, '%_\\').'%')))
                ->orderBy('scheduled_date')->orderBy('scheduled_time')
                ->get(),
            'candidates' => Candidate::query()->visibleTo($request->user())->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'filters' => $request->only(['search', 'candidate_id']),
        ]);
    }

    /**
     * The interview, its panel and the feedback the user may read (interviewers see only their own).
     */
    public function show(Request $request, Interview $interview): Response
    {
        abort_unless($interview->isVisibleTo($request->user()), 404);

        return Inertia::render('hr/recruitment/interviews/show', [
            'interview' => $interview->load([
                'candidate:id,first_name,last_name,email,phone,gender,status',
                'job:id,title,job_code',
                'round:id,name,sequence_number',
                'interviewType:id,name',
                'interviewers:id,name,email,avatar_path',
            ]),
            'feedback' => InterviewFeedback::query()->visibleTo($request->user())->where('interview_id', $interview->id)
                ->with('interviewer:id,name,email,avatar_path')->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Interview);

        return $this->done(__('Interview scheduled successfully.'));
    }

    public function update(Request $request, Interview $interview): RedirectResponse
    {
        abort_unless($interview->isVisibleTo($request->user()), 403);
        $this->save($request, $interview);

        return $this->done(__('Interview updated successfully.'));
    }

    public function updateStatus(Request $request, Interview $interview): RedirectResponse
    {
        abort_unless($interview->isVisibleTo($request->user()), 403);
        $interview->update($request->validate(['status' => ['required', Rule::in(Interview::STATUSES)]]));

        return $this->done(__('Interview status updated successfully.'));
    }

    public function destroy(Request $request, Interview $interview): RedirectResponse
    {
        abort_unless($interview->isVisibleTo($request->user()), 403);
        $interview->delete();

        return $this->done(__('Interview deleted successfully.'));
    }

    /**
     * Save the interview and its interviewers, and move an early-stage candidate to "Interview", atomically.
     */
    private function save(Request $request, Interview $interview): void
    {
        $candidate = Candidate::query()->find($request->integer('candidate_id'));

        $data = $request->validate([
            'candidate_id' => ['required', 'integer', Rule::exists('candidates', 'id')],
            'round_id' => ['required', 'integer', Rule::exists('interview_rounds', 'id')->where('job_id', $candidate?->job_id)],
            'interview_type_id' => ['required', 'integer', Rule::exists('interview_types', 'id')],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_time' => ['required', 'date_format:H:i,H:i:s'],
            'duration' => ['required', 'integer', 'min:5', 'max:600'],
            'location' => ['nullable', 'required_without:meeting_link', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'interviewers' => ['required', 'array', 'min:1'],
            'interviewers.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(Interview::STATUSES)],
        ]);

        /** @var Candidate $candidate validated above */
        DB::transaction(function () use ($interview, $data, $candidate) {
            $interview->fill(Arr::except($data, 'interviewers') + ['job_id' => $candidate->job_id])->save();
            $interview->interviewers()->sync(array_map('intval', $data['interviewers']));

            if (in_array($candidate->status, self::EARLY_STAGES, true)) {
                $candidate->update(['status' => 'Interview']);
            }
        });
    }
}
