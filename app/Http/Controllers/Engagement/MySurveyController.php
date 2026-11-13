<?php

namespace App\Http\Controllers\Engagement;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $campaigns = EngagementCampaign::listFor($employee);

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
            ...$campaign->questionnaire(),
        ]);
    }

    public function store(Request $request, EngagementCampaign $campaign): RedirectResponse
    {
        $employee = $this->answerableBy($request, $campaign);

        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        EngagementResponse::submit($campaign, $employee, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. Your answers have been submitted.')]);

        return to_route('engagement.my.index');
    }

    /**
     * The employee, if they may answer this campaign now; otherwise a redirect explaining why not.
     */
    private function answerableBy(Request $request, EngagementCampaign $campaign): Employee|RedirectResponse
    {
        $employee = $request->user()->employee ?? abort(403, __('Only employees can answer surveys.'));

        if ($refusal = $campaign->refusalFor($employee)) {
            return $this->toast('error', $refusal);
        }

        return $employee;
    }
}
