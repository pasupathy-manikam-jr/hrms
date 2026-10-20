<?php

namespace App\Http\Controllers\Benchmark;

use App\Http\Controllers\Controller;
use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use App\Support\Benchmark\SurveyAnalytics;
use App\Support\Benchmark\SurveyWorkbook;
use App\Support\Csv;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The companies whose completed workbooks were uploaded: bulk upload with a per-file report,
 * the list, and each company's own submission against the market.
 */
class SurveyParticipantController extends Controller
{
    public function index(Request $request): Response
    {
        $cycle = SurveyCycleController::current($request);
        $query = SurveyParticipant::query()->where('survey_cycle_id', $cycle?->id)->withCount('salaryRows')->withSum('salaryRows', 'headcount');

        foreach (['industry', 'state', 'employee_band'] as $field) {
            $query->when($request->input($field), fn ($q, $value) => $q->where($field, $value));
        }
        $query->when($request->input('status'), fn ($q, $status) => $status === 'needs_fixing' ? $q->whereNotNull('errors') : $q->whereNull('errors'));

        return Inertia::render('benchmark/participants/index', [
            'participants' => TableQuery::paginate($query, $request, ['company_name', 'industry', 'state'], ['company_name', 'industry', 'state', 'created_at']),
            'filters' => TableQuery::filters($request, ['cycle', 'industry', 'state', 'employee_band', 'status']),
            'cycles' => SurveyCycle::query()->latest('id')->get(['id', 'name', 'status']),
            'cycle' => $cycle?->only('id', 'name', 'status', 'min_companies'),
            'lookups' => [
                'industries' => $cycle?->lookup('IndustryList') ?? [],
                'states' => $cycle?->lookup('StateList') ?? [],
                'bands' => $cycle?->lookup('EmployeeBandList') ?? [],
            ],
        ]);
    }

    /**
     * Reads every uploaded workbook and saves each one with its errors and warnings (see importWorkbooks).
     * A company already in the cycle is only replaced when "replace" is ticked.
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'cycle_id' => ['required', Rule::exists('survey_cycles', 'id')],
            'files' => ['required', 'array', 'max:50'],
            'files.*' => ['file', 'extensions:xlsx', 'max:10240'],
            'replace' => ['boolean'],
        ], attributes: ['files.*' => __('file')]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('files');
        $report = SurveyParticipant::importWorkbooks(SurveyCycle::findOrFail($request->integer('cycle_id')), $files, $request->boolean('replace'), $request->user()?->id);

        return $this->uploadReport($report);
    }

    public function show(SurveyParticipant $participant): Response
    {
        $participant->load(['cycle', 'salaryRows' => fn ($q) => $q->orderBy('line')->orderBy('id'), 'benefits', 'attrition', 'editor:id,name']);
        $cycle = $participant->cycle;
        $market = (new SurveyAnalytics($participant->cycle))->marketMedians();

        return Inertia::render('benchmark/participants/show', [
            'participant' => $participant,
            'hasFile' => $participant->file_path !== null,
            'salaryRows' => $participant->salaryRows->map(fn ($row) => [
                ...$row->toArray(),
                'market_median' => $market[$row->job_title.'|'.$row->job_level]['median'] ?? null,
                'market_companies' => $market[$row->job_title.'|'.$row->job_level]['companies'] ?? 0,
            ]),
            'benefitLabels' => collect(SurveyWorkbook::BENEFIT_ITEMS)->mapWithKeys(fn (array $item) => [$item[0] => $item[1]]),
            // Dropdown lists and job titles for correcting the submission online.
            'lookups' => collect(['IndustryList', 'StateList', 'EmployeeBandList', 'RevenueBandList', 'OwnershipTypeList', 'ListedStatusList', 'YesNo', 'JobLevelList', 'ExperienceRequiredList', 'JobFamilyList', 'HeadcountPlanList', 'YesNoUnsure', 'RetrenchmentDriverList', 'YesNoNA'])
                ->mapWithKeys(fn (string $name) => [$name => $cycle->lookup($name)]),
            'jobTitles' => $cycle->jobs()->orderBy('title')->pluck('title'),
        ]);
    }

    /**
     * Every open error and warning in the cycle (or one submission), one per line, to send back to each company.
     */
    public function issues(Request $request): StreamedResponse
    {
        $cycle = SurveyCycleController::current($request);
        $participants = SurveyParticipant::query()->where('survey_cycle_id', $cycle?->id)
            ->when($request->integer('participant'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('company_name')->get();

        $rows = $participants->flatMap(fn (SurveyParticipant $participant) => [
            ...array_map(fn (string $issue) => [$participant->company_name, $participant->file_name, __('Error'), $issue], $participant->errors ?? []),
            ...array_map(fn (string $issue) => [$participant->company_name, $participant->file_name, __('Warning'), $issue], $participant->warnings ?? []),
        ]);

        return Csv::download(
            Str::slug(($cycle->name ?? 'survey').' '.($participants->count() === 1 ? $participants->first()?->company_name : '').' issues').'.csv',
            [__('Company'), __('File'), __('Type'), __('Issue')],
            $rows,
        );
    }

    public function download(SurveyParticipant $participant): StreamedResponse
    {
        abort_unless($participant->file_path && Storage::disk('local')->exists($participant->file_path), 404);

        return Storage::disk('local')->download($participant->file_path, $participant->file_name);
    }

    public function destroy(SurveyParticipant $participant): RedirectResponse
    {
        $participant->delete();

        return $this->done(__('Submission deleted successfully.'));
    }
}
