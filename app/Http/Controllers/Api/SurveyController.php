<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The employee's engagement surveys: open campaigns, the statements to rate, and submitting answers once.
 */
class SurveyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(EngagementCampaign::listFor($user->employee));
    }

    public function show(Request $request, EngagementCampaign $campaign): JsonResponse
    {
        $this->answerableBy($request, $campaign);

        return response()->json([
            'campaign' => $campaign->only('id', 'name', 'description', 'closes_on'),
            ...$campaign->questionnaire(),
        ]);
    }

    public function store(Request $request, EngagementCampaign $campaign): JsonResponse
    {
        EngagementResponse::submit($campaign, $this->answerableBy($request, $campaign), $request);

        return response()->json(['message' => __('Thank you. Your answers have been submitted.')], 201);
    }

    private function answerableBy(Request $request, EngagementCampaign $campaign): Employee
    {
        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee ?? abort(403, __('Only employees can answer surveys.'));

        if ($refusal = $campaign->refusalFor($employee)) {
            throw ValidationException::withMessages(['campaign' => $refusal]);
        }

        return $employee;
    }
}
