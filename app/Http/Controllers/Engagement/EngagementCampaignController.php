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
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EngagementCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EngagementCampaign::query()->withCount(['responses', 'statements'])->with('statements:engagement_statements.id')
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status));

        return Inertia::render('engagement/campaigns/index', [
            'campaigns' => TableQuery::paginate($query, $request, ['name'], ['name', 'status', 'closes_on', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
            // The question picker: the whole bank in order; inactive ones show only where a campaign already asks them.
            'statements' => EngagementStatement::query()->inBankOrder()->with('element:id,name')->get()
                ->map(fn (EngagementStatement $statement) => [
                    'id' => $statement->id,
                    'text' => $statement->text,
                    'element' => $statement->element?->name,
                    'is_active' => $statement->is_active,
                ]),
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

        [$data, $statementIds] = $this->validated($request);
        $campaign = EngagementCampaign::create([...$data, 'created_by' => $request->user()?->id]);
        $campaign->syncStatements($statementIds);
        $this->announceIfOpened($campaign, null);

        return $this->done(__('Campaign created with :count statements.', ['count' => $campaign->statements()->count()]));
    }

    public function update(Request $request, EngagementCampaign $campaign): RedirectResponse
    {
        $wasStatus = $campaign->status;
        [$data, $statementIds] = $this->validated($request, $campaign);
        $current = $campaign->statements()->pluck('engagement_statements.id')->sort()->values()->all();

        if ($statementIds !== $current) {
            // Changing the questions after people answered would make their responses incomparable.
            if ($campaign->responses()->exists()) {
                throw ValidationException::withMessages(['statement_ids' => __('Questions can no longer change: employees have already answered this campaign.')]);
            }

            $campaign->syncStatements($statementIds);
        }

        $campaign->update($data);
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
     * The campaign fields, and the chosen statement ids sorted. New picks must be active; ones the campaign already asks may stay.
     *
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function validated(Request $request, ?EngagementCampaign $campaign = null): array
    {
        $current = $campaign?->statements()->pluck('engagement_statements.id')->all() ?? [];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(EngagementCampaign::STATUSES)],
            'closes_on' => ['nullable', 'date'],
            'min_group_size' => ['required', 'integer', 'min:3', 'max:50'],
            'statement_ids' => ['required', 'array', 'min:1'],
            'statement_ids.*' => ['integer', 'distinct', Rule::exists('engagement_statements', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $current))],
        ], ['statement_ids.required' => __('Pick at least one question.'), 'statement_ids.min' => __('Pick at least one question.')]);

        /** @var list<int|string> $picked */
        $picked = Arr::pull($data, 'statement_ids');
        $statementIds = array_map('intval', $picked);
        sort($statementIds);

        return [$data, $statementIds];
    }
}
