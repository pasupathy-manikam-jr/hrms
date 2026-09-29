<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * With manage-any-interview-feedback the list covers everyone; interviewers (manage-own) see and write only
 * their own feedback, on interviews they sit on.
 */
class InterviewFeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $query = InterviewFeedback::query()
            ->visibleTo($request->user())
            ->with([
                'interview:id,candidate_id,job_id,round_id,scheduled_date,status',
                'interview.candidate:id,first_name,last_name,email',
                'interview.job:id,title',
                'interview.round:id,name',
                'interviewer:id,name,email,avatar_path',
            ])
            ->when($request->input('recommendation'), fn ($q, $value) => $q->where('recommendation', $value))
            ->when($request->integer('interviewer_id'), fn ($q, $id) => $q->where('interviewer_id', $id));

        return Inertia::render('hr/recruitment/interview-feedback/index', [
            'interviewFeedback' => TableQuery::paginate($query, $request, ['strengths', 'weaknesses', 'comments'], ['overall_rating', 'created_at']),
            'interviews' => Interview::query()->visibleTo($request->user())
                ->with(['candidate:id,first_name,last_name', 'round:id,name', 'interviewers:id,name'])
                ->latest('scheduled_date')
                ->get(['id', 'candidate_id', 'round_id', 'scheduled_date']),
            'interviewers' => User::query()->whereIn('id', DB::table('interview_interviewer')->select('user_id'))->orderBy('name')->get(['id', 'name']),
            'filters' => TableQuery::filters($request, ['recommendation', 'interviewer_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new InterviewFeedback);

        return $this->done(__('Interview feedback created successfully.'));
    }

    public function update(Request $request, InterviewFeedback $interviewFeedback): RedirectResponse
    {
        abort_unless($interviewFeedback->isVisibleTo($request->user()), 403);
        $this->save($request, $interviewFeedback);

        return $this->done(__('Interview feedback updated successfully.'));
    }

    public function destroy(Request $request, InterviewFeedback $interviewFeedback): RedirectResponse
    {
        abort_unless($interviewFeedback->isVisibleTo($request->user()), 403);

        DB::transaction(function () use ($interviewFeedback) {
            $interviewFeedback->delete();
            $this->syncSubmitted($interviewFeedback->interview_id);
        });

        return $this->done(__('Interview feedback deleted successfully.'));
    }

    private function save(Request $request, InterviewFeedback $feedback): void
    {
        $user = $request->user();
        $manageAny = $user->can('manage-any-interview-feedback');
        $rating = ['nullable', 'integer', 'between:1,5'];

        $data = $request->validate([
            'interview_id' => ['required', 'integer', $manageAny
                ? Rule::exists('interviews', 'id')
                : Rule::exists('interview_interviewer', 'interview_id')->where('user_id', $user->id)],
            'technical_rating' => $rating,
            'communication_rating' => $rating,
            'cultural_fit_rating' => $rating,
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'recommendation' => ['required', Rule::in(InterviewFeedback::RECOMMENDATIONS)],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'weaknesses' => ['nullable', 'string', 'max:2000'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ] + ($manageAny ? [
            'interviewer_id' => ['required', 'integer', Rule::exists('interview_interviewer', 'user_id')->where('interview_id', $request->integer('interview_id'))],
        ] : []));

        $data['interviewer_id'] ??= $user->id;

        DB::transaction(function () use ($feedback, $data) {
            $previous = $feedback->interview_id;
            $feedback->fill($data)->save();
            $this->syncSubmitted($feedback->interview_id);
            if ($previous && $previous !== $feedback->interview_id) {
                $this->syncSubmitted($previous);
            }
        });
    }

    private function syncSubmitted(int $interviewId): void
    {
        Interview::query()->whereKey($interviewId)->update([
            'feedback_submitted' => InterviewFeedback::query()->where('interview_id', $interviewId)->exists(),
        ]);
    }
}
