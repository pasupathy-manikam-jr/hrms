<?php

namespace App\Http\Controllers\Benchmark;

use App\Http\Controllers\Controller;
use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use App\Support\Benchmark\SurveyAnalytics;
use App\Support\Benchmark\SurveyWorkbook;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        return Inertia::render('benchmark/participants/index', [
            'participants' => TableQuery::paginate($query, $request, ['company_name', 'industry', 'state'], ['company_name', 'industry', 'state', 'created_at']),
            'filters' => TableQuery::filters($request, ['cycle', 'industry', 'state', 'employee_band']),
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
     * Reads every uploaded workbook; files without errors are saved, the rest are reported back.
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

        $cycle = SurveyCycle::findOrFail($request->integer('cycle_id'));
        $catalogue = $cycle->jobs()->get()->keyBy(fn ($job) => mb_strtolower($job->title))->map->toArray()->all();
        $reader = new SurveyWorkbook;
        $report = [];

        /** @var UploadedFile $file */
        foreach ($request->file('files') as $file) {
            $data = $reader->read($file->getRealPath(), $cycle, $catalogue);
            $company = $data['participant']['company_name'] ?? null;
            $entry = ['file' => $file->getClientOriginalName(), 'company' => $company, 'errors' => $data['errors'], 'warnings' => $data['warnings']];

            if ($data['errors'] !== []) {
                $report[] = $entry + ['status' => 'rejected'];

                continue;
            }

            $exists = SurveyParticipant::query()->where('survey_cycle_id', $cycle->id)->where('company_key', SurveyParticipant::keyFor((string) $company))->exists();

            if ($exists && ! $request->boolean('replace')) {
                $report[] = ['errors' => [__('This company has already been uploaded to this cycle. Tick "Replace existing submissions" to overwrite it.')]] + $entry + ['status' => 'skipped'];

                continue;
            }

            SurveyParticipant::record($cycle, $data, [
                'file_path' => $file->store(SurveyParticipant::UPLOAD_DIRECTORY, 'local') ?: throw new \RuntimeException('Could not store the workbook.'),
                'file_name' => $file->getClientOriginalName(),
                'uploaded_by' => $request->user()?->id,
            ]);
            $report[] = $entry + ['status' => $exists ? 'replaced' : 'imported'];
        }

        Inertia::flash('benchmarkUpload', $report);
        $saved = collect($report)->whereIn('status', ['imported', 'replaced'])->count();

        return $this->toast($saved === count($report) ? 'success' : 'warning', __(':saved of :total files imported.', ['saved' => $saved, 'total' => count($report)]));
    }

    public function show(SurveyParticipant $participant): Response
    {
        $participant->load(['cycle', 'salaryRows', 'benefits', 'attrition']);
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
        ]);
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
