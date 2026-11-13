<?php

namespace App\Http\Controllers\Engagement;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Engagement\EngagementAnswer;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The employee's side: answer each open campaign once. Answers are never shown back, to anyone.
 */
class MySurveyController extends Controller
{
    public function index(Request $request): Response
    {
        $employee = $request->user()?->employee;
        $answered = $employee
            ? EngagementResponse::query()->where('employee_id', $employee->id)->pluck('created_at', 'engagement_campaign_id')
            : collect();

        $campaigns = EngagementCampaign::query()
            ->where(fn ($q) => $q->where('status', 'open')->orWhereIn('id', $answered->keys()))
            ->withCount('statements')->latest('id')->get()
            ->map(fn (EngagementCampaign $campaign) => [
                ...$campaign->only('id', 'name', 'description', 'status', 'closes_on', 'statements_count'),
                'answered_at' => $answered[$campaign->id] ?? null,
                'can_answer' => $employee !== null && ! $answered->has($campaign->id) && $campaign->isAcceptingResponses(),
            ]);

        return Inertia::render('engagement/my/index', [
            'campaigns' => $campaigns,
            'hasEmployeeRecord' => $employee !== null,
        ]);
    }

    public function show(Request $request, EngagementCampaign $campaign): Response|RedirectResponse
    {
        $employee = $this->answerableBy($request, $campaign);

        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        return Inertia::render('engagement/my/show', [
            'campaign' => $campaign->only('id', 'name', 'description', 'closes_on'),
            'statements' => $campaign->statements()->with('element:id,name')->get()
                ->map(fn ($statement) => ['id' => $statement->id, 'text' => $statement->text, 'element' => $statement->element?->name]),
            'ratings' => collect(EngagementAnswer::RATINGS)->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request, EngagementCampaign $campaign): RedirectResponse
    {
        $employee = $this->answerableBy($request, $campaign);

        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        $statementIds = $campaign->statements()->pluck('engagement_statements.id');
        $rules = $statementIds->mapWithKeys(fn ($id) => ["answers.{$id}" => ['required', 'integer', 'between:1,4']])->all();
        $answers = $request->validate(['answers' => ['required', 'array'], ...$rules], [
            'answers.*.required' => __('Please answer this statement.'),
        ])['answers'];

        DB::transaction(function () use ($campaign, $employee, $statementIds, $answers) {
            $response = EngagementResponse::create([...EngagementResponse::snapshotOf($employee), 'engagement_campaign_id' => $campaign->id]);
            EngagementAnswer::insert($statementIds->map(fn ($id) => [
                'engagement_response_id' => $response->id,
                'engagement_statement_id' => $id,
                'rating' => (int) $answers[$id],
            ])->all());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. Your answers have been submitted.')]);

        return to_route('engagement.my.index');
    }

    /**
     * The employee, if they may answer this campaign now; otherwise a redirect explaining why not.
     */
    private function answerableBy(Request $request, EngagementCampaign $campaign): Employee|RedirectResponse
    {
        $employee = $request->user()->employee ?? abort(403, __('Only employees can answer surveys.'));

        if (! $campaign->isAcceptingResponses()) {
            return $this->toast('error', __('This survey is not open for answers.'));
        }

        if (EngagementResponse::query()->where('engagement_campaign_id', $campaign->id)->where('employee_id', $employee->id)->exists()) {
            return $this->toast('error', __('You have already answered this survey.'));
        }

        return $employee;
    }
}
