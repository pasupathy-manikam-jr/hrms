<?php

namespace App\Support\Engagement;

use App\Models\Employee;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementElement;
use App\Models\Engagement\EngagementResponse;
use App\Models\Engagement\EngagementTier;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A campaign's results. A score is the favourable share of answers (Agree or Strongly Agree, after flipping
 * reverse-scored statements), from 0 to 1. Only aggregates leave this class, and nothing is reported for a
 * group smaller than the campaign's min_group_size, so no answer can be traced back to a person.
 */
class EngagementReport
{
    /** Breakdown dimension => [response column, lookup table or null for a plain value]. */
    public const DIMENSIONS = [
        'department' => ['department_id', 'departments'],
        'designation' => ['designation_id', 'designations'],
        'branch' => ['branch_id', 'branches'],
        'gender' => ['gender', null],
        'age_band' => ['age_band', null],
        'tenure_band' => ['tenure_band', null],
    ];

    private const FAVOURABLE = 'case when (case when engagement_statements.reverse_scored = 1 then 5 - engagement_answers.rating else engagement_answers.rating end) >= 3 then 1.0 else 0.0 end';

    public function __construct(private EngagementCampaign $campaign) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $respondents = $this->campaign->responses()->count();
        $invited = Employee::query()->where('employee_status', '!=', 'terminated')->count();
        $suppressed = $respondents < $this->campaign->min_group_size;

        return [
            'respondents' => $respondents,
            'invited' => $invited,
            'response_rate' => $invited > 0 ? round($respondents / $invited, 2) : null,
            'suppressed' => $suppressed,
            'overall' => $suppressed ? null : $this->score($this->answers()),
            'elements' => $suppressed ? [] : $this->elements(),
            'tiers' => $suppressed ? [] : $this->tiers(),
            'statements' => $suppressed ? [] : $this->statements(),
        ];
    }

    /**
     * Scores per group of one dimension; groups below the minimum size are dropped and only counted.
     *
     * @return array{rows: array<int, array<string, mixed>>, hidden: int}
     */
    public function breakdown(string $dimension): array
    {
        [$column, $table] = self::DIMENSIONS[$dimension];
        $group = "engagement_responses.{$column}";

        $sizes = $this->campaign->responses()->toBase()
            ->selectRaw("{$column} as grp, count(*) as total")->groupBy($column)
            ->pluck('total', 'grp');
        $shown = $sizes->filter(fn ($total) => $total >= $this->campaign->min_group_size);

        $overall = $this->answers()->selectRaw("{$group} as grp, avg(".self::FAVOURABLE.') as score')
            ->groupBy($group)->pluck('score', 'grp');
        $byElement = $this->answers()
            ->whereNotNull('engagement_statements.engagement_element_id')
            ->selectRaw("{$group} as grp, engagement_statements.engagement_element_id as element, avg(".self::FAVOURABLE.') as score')
            ->groupBy($group, 'engagement_statements.engagement_element_id')
            ->get()->groupBy('grp');
        $labels = $table ? DB::table($table)->whereIn('id', $shown->keys()->filter())->pluck('name', 'id') : collect();

        $rows = $shown->keys()->map(fn ($key) => [
            'label' => $key === '' ? __('Not recorded') : ($table ? (string) ($labels[$key] ?? __('Removed')) : ucfirst((string) $key)),
            'respondents' => (int) $shown[$key],
            'overall' => round((float) $overall[$key], 2),
            'elements' => collect($byElement[$key] ?? [])->mapWithKeys(fn ($row) => [(int) $row->element => round((float) $row->score, 2)])->all(),
        ]);

        $order = match ($dimension) {
            'age_band' => EngagementResponse::AGE_BANDS,
            'tenure_band' => EngagementResponse::TENURE_BANDS,
            default => null,
        };
        $rows = $order
            ? $rows->sortBy(fn ($row) => array_search($row['label'], $order, true) === false ? 99 : array_search($row['label'], $order, true))
            : $rows->sortBy('label');

        return ['rows' => $rows->values()->all(), 'hidden' => $sizes->count() - $shown->count()];
    }

    /**
     * @return array<int, array{id: int, name: string, score: float}>
     */
    private function elements(): array
    {
        $scores = $this->answers()
            ->selectRaw('engagement_statements.engagement_element_id as element, avg('.self::FAVOURABLE.') as score')
            ->whereNotNull('engagement_statements.engagement_element_id')
            ->groupBy('engagement_statements.engagement_element_id')
            ->pluck('score', 'element');

        return EngagementElement::query()->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (EngagementElement $element) => $scores->has($element->id))
            ->map(fn (EngagementElement $element) => [
                'id' => $element->id,
                'name' => $element->name,
                'score' => round((float) $scores[$element->id], 2),
            ])->values()->all();
    }

    /**
     * How many respondents fall in each tier, by their own overall score.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tiers(): array
    {
        $scores = $this->answers()->selectRaw('engagement_answers.engagement_response_id as response, avg('.self::FAVOURABLE.') as score')
            ->groupBy('engagement_answers.engagement_response_id')->pluck('score');
        $tiers = EngagementTier::query()->orderByDesc('min_score')->get();
        $counts = $scores->countBy(fn ($score) => self::tierFor(round((float) $score, 2), $tiers)->level ?? 0);

        return $tiers->map(fn (EngagementTier $tier) => [
            ...$tier->only('level', 'name', 'min_score', 'need', 'profile', 'action'),
            'count' => $counts[$tier->level] ?? 0,
            'share' => $scores->count() > 0 ? round(($counts[$tier->level] ?? 0) / $scores->count(), 2) : 0,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, EngagementTier>  $tiersHighestFirst
     */
    public static function tierFor(float $score, Collection $tiersHighestFirst): ?EngagementTier
    {
        return $tiersHighestFirst->first(fn (EngagementTier $tier) => $score >= (float) $tier->min_score);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statements(): array
    {
        return $this->answers()
            ->leftJoin('engagement_elements', 'engagement_elements.id', '=', 'engagement_statements.engagement_element_id')
            ->selectRaw('engagement_statements.id, engagement_statements.text, engagement_elements.name as element, avg('.self::FAVOURABLE.') as score')
            ->groupBy('engagement_statements.id', 'engagement_statements.text', 'engagement_elements.name')
            ->orderBy('score')
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'text' => $row->text, 'element' => $row->element, 'score' => round((float) $row->score, 2)])
            ->all();
    }

    private function score(Builder $answers): ?float
    {
        $score = $answers->selectRaw('avg('.self::FAVOURABLE.') as score')->value('score');

        return $score === null ? null : round((float) $score, 2);
    }

    /**
     * This campaign's answers joined to their statement and response.
     */
    private function answers(): Builder
    {
        return DB::table('engagement_answers')
            ->join('engagement_responses', 'engagement_responses.id', '=', 'engagement_answers.engagement_response_id')
            ->join('engagement_statements', 'engagement_statements.id', '=', 'engagement_answers.engagement_statement_id')
            ->where('engagement_responses.engagement_campaign_id', $this->campaign->id);
    }
}
