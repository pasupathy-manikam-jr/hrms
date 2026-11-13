<?php

namespace App\Http\Controllers\Engagement;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementElement;
use App\Models\Engagement\EngagementStatement;
use App\Support\Engagement\EngagementReport;
use App\Support\Push;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EngagementCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EngagementCampaign::query()->withCount(['responses', 'statements'])
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status));

        return Inertia::render('engagement/campaigns/index', [
            'campaigns' => TableQuery::paginate($query, $request, ['name'], ['name', 'status', 'closes_on', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
            'activeStatements' => EngagementStatement::query()->where('is_active', true)->count(),
        ]);
    }

    /**
     * The campaign's results, broken down by the chosen dimension.
     */
    public function show(Request $request, EngagementCampaign $campaign): Response
    {
        $by = $request->string('by')->toString();
        $by = array_key_exists($by, EngagementReport::DIMENSIONS) ? $by : 'department';
        $report = new EngagementReport($campaign);
        $summary = $report->summary();

        return Inertia::render('engagement/campaigns/show', [
            'campaign' => $campaign->loadCount('statements'),
            'summary' => $summary,
            'by' => $by,
            'dimensions' => array_keys(EngagementReport::DIMENSIONS),
            'breakdown' => $summary['suppressed'] ? null : $report->breakdown($by),
            'elements' => EngagementElement::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! EngagementStatement::query()->where('is_active', true)->exists()) {
            return $this->toast('error', __('Add statements to the question bank first.'));
        }

        $campaign = EngagementCampaign::create([...$this->validated($request), 'created_by' => $request->user()?->id]);
        $campaign->attachActiveStatements();
        $this->announceIfOpened($campaign, null);

        return $this->done(__('Campaign created with :count statements.', ['count' => $campaign->statements()->count()]));
    }

    public function update(Request $request, EngagementCampaign $campaign): RedirectResponse
    {
        $wasStatus = $campaign->status;
        $campaign->update($this->validated($request));
        $this->announceIfOpened($campaign, $wasStatus);

        return $this->done(__('Campaign updated successfully.'));
    }

    /**
     * Tell every employee when a campaign opens for answers.
     */
    private function announceIfOpened(EngagementCampaign $campaign, ?string $wasStatus): void
    {
        if ($campaign->status === 'open' && $wasStatus !== 'open') {
            Push::send(Employee::query()->where('employee_status', '!=', 'terminated')->pluck('user_id'),
                'New survey', ':name is open for your answers.', ['name' => $campaign->name], ['type' => 'survey', 'id' => $campaign->id]);
        }
    }

    public function destroy(EngagementCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return $this->done(__('Campaign deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(EngagementCampaign::STATUSES)],
            'closes_on' => ['nullable', 'date'],
            'min_group_size' => ['required', 'integer', 'min:3', 'max:50'],
        ]);
    }
}
