<?php

namespace App\Support\Benchmark;

use App\Models\Benchmark\SurveyAttrition;
use App\Models\Benchmark\SurveyBenefit;
use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use App\Models\Benchmark\SurveySalaryRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Pooled statistics for one cycle, cut by any profile or job filter. A figure is only returned when at
 * least the cycle's `min_companies` companies contribute to it (null otherwise), so no company's own
 * answers can be singled out.
 */
class SurveyAnalytics
{
    /** Filters on the company profile (survey_participants columns). */
    public const PROFILE_FILTERS = ['industry', 'state', 'employee_band', 'revenue_band', 'ownership_type', 'listed_status', 'unionised'];

    /** Filters on the salary rows. */
    public const JOB_FILTERS = ['job_family', 'job_title', 'job_level'];

    public const ALLOWANCES = [
        'transport' => 'Transport', 'meal' => 'Meal', 'housing' => 'Housing', 'shift' => 'Shift / Night',
        'phone' => 'Phone / Internet', 'overtime' => 'Overtime', 'outstation' => 'Outstation / Travel', 'other' => 'Other',
    ];

    /** Benefit items answered Yes/No; the rest are numbers (days, %, RM, months) or free text. */
    private const YES_NO_BENEFITS = [
        'outpatient_coverage', 'hospitalisation_coverage', 'dental_coverage', 'optical_coverage', 'group_life_insurance',
        'personal_accident_insurance', 'kpi_incentive_scheme', 'profit_sharing_scheme', 'employee_share_scheme', 'flexible_work',
    ];

    private const TEXT_BENEFITS = ['other_incentive_notes', 'other_benefit'];

    /** @var EloquentCollection<int, SurveyParticipant> */
    private EloquentCollection $participants;

    /**
     * @param  array<string, string|null>  $filters  profile + job filters, plus `gender` (male|female) and `weighting` (company|incumbent)
     */
    public function __construct(private SurveyCycle $cycle, private array $filters = [])
    {
        $this->participants = SurveyParticipant::query()
            ->where('survey_cycle_id', $cycle->id)
            ->where(function (Builder $query) {
                foreach (self::PROFILE_FILTERS as $field) {
                    if (filled($this->filters[$field] ?? null)) {
                        $query->where($field, $this->filters[$field]);
                    }
                }
            })
            ->get();
    }

    public function companyCount(): int
    {
        return $this->participants->count();
    }

    /**
     * Headline numbers for the filtered companies.
     *
     * @return array{companies: int, roles: int, incumbents: int, suppressed: bool}
     */
    public function overview(): array
    {
        $rows = $this->salaryRows();

        return [
            'companies' => $this->companyCount(),
            'roles' => $rows->unique(fn (SurveySalaryRow $row) => $row->job_title.'|'.$row->job_level)->count(),
            'incumbents' => (int) $rows->sum('headcount'),
            'suppressed' => $this->companyCount() < $this->cycle->min_companies,
        ];
    }

    /**
     * Base salary per standard job title and level: P25 / median / P75 / average, plus bonus,
     * allowances and total monthly cash (base + allowances + guaranteed bonus / 12).
     *
     * @return array<int, array<string, mixed>>
     */
    public function salaries(): array
    {
        $gender = $this->filters['gender'] ?? null;
        $byIncumbent = ($this->filters['weighting'] ?? 'company') === 'incumbent';

        return $this->salaryRows()
            ->groupBy(fn (SurveySalaryRow $row) => $row->job_title.'|'.$row->job_level)
            ->map(function (Collection $rows) use ($gender, $byIncumbent) {
                /** @var SurveySalaryRow $first */
                $first = $rows->first();
                $points = $rows->map(fn (SurveySalaryRow $row) => [
                    'value' => (float) match ($gender) {
                        'male' => $row->avg_male,
                        'female' => $row->avg_female,
                        default => $row->median_salary,
                    },
                    'weight' => $byIncumbent ? (int) match ($gender) {
                        'male' => $row->male,
                        'female' => $row->female,
                        default => $row->headcount,
                    } : 1,
                    'row' => $row,
                ])->filter(fn (array $point) => $point['value'] > 0 && $point['weight'] > 0);

                $companies = $points->pluck('row.survey_participant_id')->unique()->count();
                $stats = [
                    'job_family' => $first->job_family,
                    'job_title' => $first->job_title,
                    'job_level' => $first->job_level,
                    'companies' => $companies,
                    'incumbents' => (int) $rows->sum('headcount'),
                ];

                if ($companies < $this->cycle->min_companies) {
                    return $stats + ['suppressed' => true];
                }

                $values = $points->flatMap(fn (array $point) => array_fill(0, $point['weight'], $point['value']))->all();
                $median = self::percentile($values, 50);
                $allowances = self::average($rows->pluck('total_allowances')->map(fn ($value) => (float) $value)->all());
                $bonus = self::average($rows->pluck('guaranteed_bonus_months')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->all());

                return $stats + [
                    'suppressed' => false,
                    'p25' => self::percentile($values, 25),
                    'median' => $median,
                    'p75' => self::percentile($values, 75),
                    'average' => self::average($values),
                    'min' => round((float) collect($values)->min(), 2),
                    'max' => round((float) collect($values)->max(), 2),
                    'bonus_months' => $bonus,
                    'allowances' => $allowances,
                    'total_cash' => $median === null ? null : round($median + (float) $allowances + $median * (float) $bonus / 12, 2),
                ];
            })
            ->sortBy([['job_family', 'asc'], ['job_title', 'asc'], ['job_level', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Average male vs female base pay and gender mix, by job level and by job family.
     *
     * @return array{levels: array<int, array<string, mixed>>, families: array<int, array<string, mixed>>}
     */
    public function genderPay(): array
    {
        $group = fn (string $field) => $this->salaryRows()
            ->groupBy($field)
            ->map(function (Collection $rows, string $name) {
                $companies = $rows->pluck('survey_participant_id')->unique()->count();
                $male = (int) $rows->sum('male');
                $female = (int) $rows->sum('female');
                $stats = ['name' => $name, 'companies' => $companies, 'male' => $male, 'female' => $female];

                if ($companies < $this->cycle->min_companies) {
                    return $stats + ['suppressed' => true];
                }

                $avgMale = self::weightedAverage($rows, 'avg_male', 'male');
                $avgFemale = self::weightedAverage($rows, 'avg_female', 'female');

                return $stats + [
                    'suppressed' => false,
                    'avg_male' => $avgMale,
                    'avg_female' => $avgFemale,
                    // Positive = women are paid less than men.
                    'gap_percent' => $avgMale && $avgFemale ? round(($avgMale - $avgFemale) / $avgMale * 100, 1) : null,
                    'female_share' => $male + $female > 0 ? round($female / ($male + $female) * 100, 1) : null,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        return ['levels' => $group('job_level'), 'families' => $group('job_family')];
    }

    /**
     * Per allowance type: share of companies paying it and the average monthly amount where paid.
     *
     * @return array{types: array<int, array<string, mixed>>, notes: array<int, string>}
     */
    public function allowances(): array
    {
        $byCompany = $this->salaryRows()->groupBy('survey_participant_id');
        $companies = $byCompany->count();
        $suppressed = $companies < $this->cycle->min_companies;

        $types = collect(self::ALLOWANCES)->map(function (string $label, string $type) use ($byCompany, $companies, $suppressed) {
            $paying = $byCompany->filter(fn (Collection $rows) => $rows->contains(fn (SurveySalaryRow $row) => (float) $row->{"allowance_{$type}"} > 0));
            $amounts = $this->salaryRows()->map(fn (SurveySalaryRow $row) => (float) $row->{"allowance_{$type}"})->filter(fn (float $value) => $value > 0)->all();

            return [
                'type' => $type,
                'label' => $label,
                'companies' => $paying->count(),
                'prevalence' => $suppressed || $companies === 0 ? null : round($paying->count() / $companies * 100, 1),
                'average' => $suppressed || $paying->count() < $this->cycle->min_companies ? null : self::average($amounts),
                'median' => $suppressed || $paying->count() < $this->cycle->min_companies ? null : self::percentile($amounts, 50),
            ];
        })->values()->all();

        $notes = $suppressed ? [] : $this->salaryRows()->pluck('other_allowance_note')->filter()->unique()->sort()->values()->all();

        return ['types' => $types, 'notes' => $notes];
    }

    /**
     * Per benefit item: % of companies saying Yes, or the median value (company-wide, or per tier).
     *
     * @return array<int, array<string, mixed>>
     */
    public function benefits(): array
    {
        $answers = SurveyBenefit::query()->whereIn('survey_participant_id', $this->participants->modelKeys())->get()->groupBy('item');
        $min = $this->cycle->min_companies;

        return collect(SurveyWorkbook::BENEFIT_ITEMS)->values()->map(function (array $item) use ($answers, $min) {
            [$key, $label] = $item;
            /** @var Collection<int, SurveyBenefit> $rows */
            $rows = $answers->get($key, collect());
            $kind = in_array($key, self::YES_NO_BENEFITS, true) ? 'yes_no' : (in_array($key, self::TEXT_BENEFITS, true) ? 'text' : 'number');
            $stat = ['item' => $key, 'label' => $label, 'kind' => $kind, 'companies' => $rows->count()];

            if ($rows->count() < $min) {
                return $stat + ['suppressed' => true];
            }

            // A tiered answer counts for each tier; a company-wide answer counts for both.
            $tier = fn (string $column) => $rows->map(fn (SurveyBenefit $row) => $row->{$column} ?? $row->company_value)->filter(fn ($value) => filled($value));

            return $stat + ['suppressed' => false] + match ($kind) {
                'yes_no' => [
                    'yes_percent' => self::yesShare($rows->map(fn (SurveyBenefit $row) => $row->company_value ?? $row->exec_value ?? $row->manager_value)),
                    'exec' => self::yesShare($tier('exec_value')),
                    'manager' => self::yesShare($tier('manager_value')),
                ],
                'number' => [
                    'median' => self::percentile(self::numbers($rows->flatMap(fn (SurveyBenefit $row) => [$row->company_value, $row->exec_value, $row->manager_value])), 50),
                    'exec' => self::percentile(self::numbers($tier('exec_value')), 50),
                    'manager' => self::percentile(self::numbers($tier('manager_value')), 50),
                    'tiered_percent' => round($rows->where('same_for_all', false)->count() / $rows->count() * 100, 1),
                ],
                default => ['notes' => $rows->map(fn (SurveyBenefit $row) => $row->company_value ?? $row->remarks)->filter()->values()->all()],
            };
        })->all();
    }

    /**
     * Attrition medians, hardest-to-hire/retain ranking (weighted 3-2-1), headcount plans and retrenchment.
     *
     * @return array<string, mixed>
     */
    public function attrition(): array
    {
        $records = SurveyAttrition::query()->whereIn('survey_participant_id', $this->participants->modelKeys())->get();
        $companies = $records->count();

        if ($companies < $this->cycle->min_companies) {
            return ['companies' => $companies, 'suppressed' => true];
        }

        // Per 100 staff, using the headcount each company reported in Salary Data.
        $headcounts = $this->participants->mapWithKeys(fn (SurveyParticipant $participant) => [
            $participant->id => (int) $participant->salaryRows()->sum('headcount'),
        ]);
        $per100 = fn (string $field) => self::percentile($records
            ->filter(fn (SurveyAttrition $record) => $record->{$field} !== null && ($headcounts[$record->survey_participant_id] ?? 0) > 0)
            ->map(fn (SurveyAttrition $record) => $record->{$field} / $headcounts[$record->survey_participant_id] * 100)
            ->all(), 50);

        $ranking = fn (string $group) => $records
            ->flatMap(fn (SurveyAttrition $record) => [
                [$record->{"{$group}_1"}, 3], [$record->{"{$group}_2"}, 2], [$record->{"{$group}_3"}, 1],
            ])
            ->filter(fn (array $pick) => $pick[0] !== null)
            ->groupBy(fn (array $pick): string => (string) $pick[0])
            ->map(fn (Collection $picks, string $family) => ['name' => $family, 'score' => $picks->sum(fn (array $pick) => $pick[1]), 'mentions' => $picks->count()])
            ->sortByDesc('score')
            ->values()
            ->take(10)
            ->all();

        $shares = fn (string $field) => $records->pluck($field)->filter(fn ($value) => $value !== null)->countBy()
            ->map(fn (int $count) => round($count / $companies * 100, 1))->sortDesc()->all();

        return [
            'companies' => $companies,
            'suppressed' => false,
            'attrition_rate' => self::percentile(self::numbers($records->pluck('attrition_rate')), 50),
            'new_hire_attrition_rate' => self::percentile(self::numbers($records->pluck('new_hire_attrition_rate')), 50),
            'retirements_per_100' => $per100('retirements'),
            'terminations_per_100' => $per100('involuntary_terminations'),
            'non_renewals_per_100' => $per100('contract_non_renewals'),
            'time_to_fill_days' => self::percentile(self::numbers($records->pluck('time_to_fill_days')), 50),
            'hardest_to_hire' => $ranking('hardest_to_hire'),
            'hardest_to_retain' => $ranking('hardest_to_retain'),
            'headcount_plan' => $shares('headcount_plan'),
            'pay_reason_for_leaving' => $shares('pay_reason_for_leaving'),
            'retrenched_percent' => round($records->where('retrenched', true)->count() / $companies * 100, 1),
            'retrenchment_drivers' => $records->pluck('retrenchment_driver')->filter()->countBy()->sortDesc()->all(),
            'retrenchment_above_statutory' => $shares('retrenchment_above_statutory'),
        ];
    }

    /**
     * Tenure mix, experience required to hire and share of shift-based roles.
     *
     * @return array<string, mixed>
     */
    public function workforce(): array
    {
        $rows = $this->salaryRows();

        if ($rows->pluck('survey_participant_id')->unique()->count() < $this->cycle->min_companies) {
            return ['suppressed' => true];
        }

        $total = max(1, (int) $rows->sum('headcount'));
        $answered = $rows->whereNotNull('shift_based');

        return [
            'suppressed' => false,
            'tenure' => collect(['tenure_under_1' => '< 1 year', 'tenure_1_2' => '1-2 years', 'tenure_3_4' => '3-4 years', 'tenure_5_plus' => '5+ years'])
                ->map(fn (string $label, string $field) => ['label' => $label, 'headcount' => (int) $rows->sum($field), 'percent' => round($rows->sum($field) / $total * 100, 1)])
                ->values()->all(),
            'experience' => $rows->pluck('experience_required')->filter()->countBy()
                ->map(fn (int $count, string $label) => ['label' => $label, 'roles' => $count, 'percent' => round($count / $rows->count() * 100, 1)])
                ->values()->all(),
            'shift_based_percent' => $answered->isEmpty() ? null : round($answered->where('shift_based', true)->count() / $answered->count() * 100, 1),
            'levels' => $rows->groupBy('job_level')->map(fn (Collection $group, string $level) => ['label' => $level, 'headcount' => (int) $group->sum('headcount')])->values()->all(),
        ];
    }

    /**
     * How many companies are in each profile category (the sample's make-up).
     *
     * @return array<string, array<array-key, int>>
     */
    public function participantMix(): array
    {
        $mix = [];
        foreach (['industry', 'state', 'employee_band', 'ownership_type'] as $field) {
            $mix[$field] = $this->participants->pluck($field)->filter()->countBy()->sortDesc()->all();
        }

        return $mix;
    }

    /**
     * The market median base salary per "title|level" over the whole cycle (for a participant's own comparison).
     *
     * @return array<string, array{median: float|null, companies: int}>
     */
    public function marketMedians(): array
    {
        return collect($this->salaries())->mapWithKeys(fn (array $row) => [
            $row['job_title'].'|'.$row['job_level'] => ['median' => $row['median'] ?? null, 'companies' => $row['companies']],
        ])->all();
    }

    /**
     * @return Collection<int, SurveySalaryRow>
     */
    private function salaryRows(): Collection
    {
        return once(fn () => SurveySalaryRow::query()
            ->whereIn('survey_participant_id', $this->participants->modelKeys())
            ->where(function (Builder $query) {
                foreach (self::JOB_FILTERS as $field) {
                    if (filled($this->filters[$field] ?? null)) {
                        $query->where($field, $this->filters[$field]);
                    }
                }
            })
            ->get());
    }

    /**
     * Excel's PERCENTILE.INC (linear interpolation between the closest ranks).
     *
     * @param  array<int, float>  $values
     */
    public static function percentile(array $values, float $percent): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $rank = ($percent / 100) * (count($values) - 1);
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);

        return round($values[$lower] + ($values[$upper] - $values[$lower]) * ($rank - $lower), 2);
    }

    /**
     * @param  array<int, float>  $values
     */
    private static function average(array $values): ?float
    {
        return $values === [] ? null : round(array_sum($values) / count($values), 2);
    }

    /**
     * @param  Collection<int, SurveySalaryRow>  $rows
     */
    private static function weightedAverage(Collection $rows, string $value, string $weight): ?float
    {
        $rows = $rows->filter(fn (SurveySalaryRow $row) => $row->{$value} !== null && $row->{$weight} > 0);
        $total = $rows->sum($weight);

        return $total > 0 ? round($rows->sum(fn (SurveySalaryRow $row) => $row->{$value} * $row->{$weight}) / $total, 2) : null;
    }

    /**
     * Numeric answers only ("14 days", "RM1,200" → 14, 1200); text answers are ignored.
     *
     * @param  Collection<int, mixed>  $values
     * @return array<int, float>
     */
    private static function numbers(Collection $values): array
    {
        return $values
            ->map(fn ($value) => is_numeric($value) ? (float) $value : (preg_match('/^\D{0,3}([\d,]+(?:\.\d+)?)/', (string) $value, $match) ? (float) str_replace(',', '', $match[1]) : null))
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private static function yesShare(Collection $values): ?float
    {
        $answers = $values->filter(fn ($value) => in_array(strtolower((string) $value), ['yes', 'no'], true));

        return $answers->isEmpty() ? null : round($answers->filter(fn ($value) => strtolower((string) $value) === 'yes')->count() / $answers->count() * 100, 1);
    }
}
