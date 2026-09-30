<?php

namespace App\Http\Controllers\Benchmark;

use App\Http\Controllers\Controller;
use App\Models\Benchmark\SurveyCycle;
use App\Support\Benchmark\SurveyAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The analytics explorer: every view is computed for the chosen cycle and filters, with the cycle's
 * confidentiality threshold applied, and can be exported to Excel or a PDF report.
 */
class SurveyAnalyticsController extends Controller
{
    private const FILTERS = [...SurveyAnalytics::PROFILE_FILTERS, ...SurveyAnalytics::JOB_FILTERS, 'gender', 'weighting', 'confidentiality'];

    /** Profile filters the comparison may override, sent as vs_<filter>; "*" removes the filter (whole market). */
    private const COMPARE_FILTERS = ['industry', 'state', 'employee_band', 'ownership_type'];

    public function index(Request $request): Response
    {
        $cycle = SurveyCycleController::current($request);
        $filters = $this->filters($request);
        $analytics = $cycle ? new SurveyAnalytics($cycle, $filters) : null;
        $overrides = collect(self::COMPARE_FILTERS)
            ->mapWithKeys(fn (string $field) => [$field => $request->string("vs_{$field}")->toString()])
            ->filter(fn (string $value) => $value !== '');

        return Inertia::render('benchmark/analytics/index', [
            'cycles' => SurveyCycle::query()->latest('id')->get(['id', 'name']),
            'cycle' => $cycle?->only('id', 'name', 'min_companies'),
            'filters' => $request->only(['cycle', 'view', ...self::FILTERS, ...array_map(fn (string $field) => "vs_{$field}", self::COMPARE_FILTERS)]),
            'options' => $cycle ? $this->options($cycle) : [],
            'report' => $analytics ? $this->report($analytics) : null,
            'comparison' => $cycle && $overrides->isNotEmpty()
                ? $this->comparison(new SurveyAnalytics($cycle, array_filter([...$filters, ...$overrides->all()], fn (string $value) => $value !== '*')))
                : null,
        ]);
    }

    /**
     * Every view as a sheet of one workbook, for the current filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $cycle = SurveyCycleController::current($request) ?? abort(404);
        $report = $this->report(new SurveyAnalytics($cycle, $this->filters($request)));
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        foreach ($this->tables($report) as $title => $rows) {
            $sheet = $book->createSheet();
            $sheet->setTitle(Str::limit($title, 31, ''));
            $sheet->fromArray($rows, strictNullComparison: true);
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            'benchmark-'.Str::slug(str_replace('/', '-', $cycle->name)).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function pdf(Request $request): HttpResponse
    {
        $cycle = SurveyCycleController::current($request) ?? abort(404);
        $filters = array_filter($this->filters($request));
        $report = $this->report(new SurveyAnalytics($cycle, $filters));

        return Pdf::loadView('benchmark.report', [
            'cycle' => $cycle,
            'filters' => $filters,
            'report' => $report,
            'tables' => $this->tables($report),
            'minCompanies' => (new SurveyAnalytics($cycle, $filters))->minCompanies,
            'brand' => config('app.name'),
        ])->setPaper('a4', 'landscape')->download('benchmark-report-'.Str::slug(str_replace('/', '-', $cycle->name)).'.pdf');
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return array_filter($request->only(self::FILTERS), fn ($value) => is_string($value) && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function report(SurveyAnalytics $analytics): array
    {
        return [
            'overview' => $analytics->overview(),
            'salaries' => $analytics->salaries(),
            'gender' => $analytics->genderPay(),
            'allowances' => $analytics->allowances(),
            'benefits' => $analytics->benefits(),
            'attrition' => $analytics->attrition(),
            'workforce' => $analytics->workforce(),
            'mix' => $analytics->participantMix(),
        ];
    }

    /**
     * The second cut for side-by-side comparison: its size and salary medians by "title|level".
     *
     * @return array{overview: array<string, mixed>, salaries: array<string, array<string, mixed>>}
     */
    private function comparison(SurveyAnalytics $analytics): array
    {
        return [
            'overview' => $analytics->overview(),
            'salaries' => collect($analytics->salaries())->mapWithKeys(fn (array $row) => [
                $row['job_title'].'|'.$row['job_level'] => ['median' => $row['median'] ?? null, 'companies' => $row['companies'], 'suppressed' => $row['suppressed']],
            ])->all(),
        ];
    }

    /**
     * Filter choices from the cycle's own dropdown lists.
     *
     * @return array<string, array<int, mixed>>
     */
    private function options(SurveyCycle $cycle): array
    {
        return [
            'industry' => $cycle->lookup('IndustryList'),
            'state' => $cycle->lookup('StateList'),
            'employee_band' => $cycle->lookup('EmployeeBandList'),
            'revenue_band' => $cycle->lookup('RevenueBandList'),
            'ownership_type' => $cycle->lookup('OwnershipTypeList'),
            'listed_status' => $cycle->lookup('ListedStatusList'),
            'unionised' => $cycle->lookup('YesNo'),
            'job_family' => $cycle->lookup('JobFamilyList'),
            'job_level' => $cycle->lookup('JobLevelList'),
            'job_title' => $cycle->jobs()->distinct()->orderBy('title')->pluck('title')->all(),
        ];
    }

    /**
     * The report as plain tables (header row first) for Excel and PDF; suppressed figures read "n < min".
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array<int, array<int, mixed>>>
     */
    private function tables(array $report): array
    {
        $hidden = __('Insufficient data');
        $value = fn (array $row, string $key) => ($row['suppressed'] ?? false) ? $hidden : ($row[$key] ?? null);

        $tables = [
            __('Salary benchmarks') => [
                [__('Job Family'), __('Job Title'), __('Job Level'), __('Companies'), __('Incumbents'), 'P25', __('Median'), 'P75', __('Average'), __('Min'), __('Max'), __('Bonus (months)'), __('Allowances'), __('Total cash')],
                ...array_map(fn (array $row) => [
                    $row['job_family'], $row['job_title'], $row['job_level'], $row['companies'], $row['incumbents'],
                    $value($row, 'p25'), $value($row, 'median'), $value($row, 'p75'), $value($row, 'average'), $value($row, 'min'),
                    $value($row, 'max'), $value($row, 'bonus_months'), $value($row, 'allowances'), $value($row, 'total_cash'),
                ], $report['salaries']),
            ],
        ];

        foreach (['levels' => __('Gender pay by level'), 'families' => __('Gender pay by family')] as $key => $title) {
            $tables[$title] = [
                [__('Group'), __('Companies'), __('Male'), __('Female'), __('Avg. male'), __('Avg. female'), __('Pay gap %'), __('Female %')],
                ...array_map(fn (array $row) => [
                    $row['name'], $row['companies'], $row['male'], $row['female'], $value($row, 'avg_male'),
                    $value($row, 'avg_female'), $value($row, 'gap_percent'), $value($row, 'female_share'),
                ], $report['gender'][$key]),
            ];
        }

        $tables[__('Allowances')] = [
            [__('Allowance'), __('Companies paying'), __('% of companies'), __('Average (RM)'), __('Median (RM)')],
            ...array_map(fn (array $row) => [__($row['label']), $row['companies'], $row['prevalence'] ?? $hidden, $row['average'] ?? $hidden, $row['median'] ?? $hidden], $report['allowances']['types']),
        ];

        $tables[__('Benefits')] = [
            [__('Benefit'), __('Companies'), __('% Yes / Median'), __('Non-Exec / Executive'), __('Managerial & Above')],
            ...array_map(fn (array $row) => [
                __($row['label']), $row['companies'],
                ...match (true) {
                    $row['suppressed'] => [$hidden, null, null],
                    $row['kind'] === 'yes_no' => [$row['yes_percent'], $row['exec'], $row['manager']],
                    $row['kind'] === 'number' => [$row['median'], $row['exec'], $row['manager']],
                    default => [implode('; ', $row['notes']), null, null],
                },
            ], $report['benefits']),
        ];

        $attrition = $report['attrition'];
        $tables[__('Attrition & hiring')] = $attrition['suppressed']
            ? [[__('Measure'), __('Value')], [__('Companies'), $attrition['companies']], [__('All figures'), $hidden]]
            : [
                [__('Measure'), __('Value')],
                [__('Companies'), $attrition['companies']],
                [__('Median attrition rate %'), $attrition['attrition_rate']],
                [__('Median new-hire attrition rate %'), $attrition['new_hire_attrition_rate']],
                [__('Retirements per 100 staff'), $attrition['retirements_per_100']],
                [__('Involuntary terminations per 100 staff'), $attrition['terminations_per_100']],
                [__('Contract non-renewals per 100 staff'), $attrition['non_renewals_per_100']],
                [__('Median time to fill (days)'), $attrition['time_to_fill_days']],
                [__('Companies that retrenched %'), $attrition['retrenched_percent']],
                ...array_map(fn (array $row) => [__('Hardest to hire').': '.$row['name'], $row['score']], $attrition['hardest_to_hire']),
                ...array_map(fn (array $row) => [__('Hardest to retain').': '.$row['name'], $row['score']], $attrition['hardest_to_retain']),
                ...array_map(fn ($share, $plan) => [__('Headcount plan').': '.$plan, $share], $attrition['headcount_plan'], array_keys($attrition['headcount_plan'])),
            ];

        /** @var array<string, array<string, int>> $mix */
        $mix = $report['mix'];
        $tables[__('Participants')] = [
            [__('Category'), __('Value'), __('Companies')],
            ...collect($mix)->flatMap(fn (array $counts, string $field) => array_map(
                fn ($count, $name) => [__(Str::headline($field)), $name, $count], $counts, array_keys($counts),
            ))->all(),
        ];

        return $tables;
    }
}
