<?php

namespace App\Http\Controllers\Benchmark;

use App\Http\Controllers\Controller;
use App\Models\Benchmark\BenchmarkJob;
use App\Models\Benchmark\SurveyBenefit;
use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use App\Support\Benchmark\SurveyWorkbook;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Survey editions. Each holds the blank template its workbooks follow, which supplies the dropdown lists
 * and the standard job catalogue used to check uploads.
 */
class SurveyCycleController extends Controller
{
    public const TEMPLATE_DIRECTORY = 'benchmark/templates';

    public function index(Request $request): Response
    {
        return Inertia::render('benchmark/cycles/index', [
            'cycles' => TableQuery::paginate(
                SurveyCycle::query()->withCount(['participants', 'jobs']),
                $request, ['name'], ['name', 'created_at'],
            ),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $file = $request->file('template');
        $file = $file instanceof UploadedFile ? $file : null;
        /** @var list<UploadedFile> $workbooks */
        $workbooks = $request->file('files', []);
        // Every completed workbook carries the template's Lookups sheet, so the blank template is optional.
        $template = $file ? $this->readTemplate($file) : $this->readTemplate($workbooks[0], 'files', blank: false);

        $cycle = DB::transaction(function () use ($request, $data, $file, $template) {
            $cycle = SurveyCycle::create([...$data, 'created_by' => $request->user()?->id]);
            $this->saveTemplate($cycle, $file, $template);

            return $cycle;
        });

        return $workbooks === []
            ? $this->done(__('Survey cycle created successfully.'))
            : $this->uploadReport(SurveyParticipant::importWorkbooks($cycle, $workbooks, false, $request->user()?->id));
    }

    public function update(Request $request, SurveyCycle $cycle): RedirectResponse
    {
        $data = $this->validated($request, false, $cycle);
        $file = $request->file('template');
        $file = $file instanceof UploadedFile ? $file : null;
        $template = $file ? $this->readTemplate($file) : null;

        DB::transaction(function () use ($cycle, $data, $file, $template) {
            $cycle->update($data);

            if ($file && $template) {
                $this->saveTemplate($cycle, $file, $template);
                $this->clearGuidanceRemarks($cycle);
            }
        });

        return $this->done(__('Survey cycle updated successfully.'));
    }

    public function destroy(SurveyCycle $cycle): RedirectResponse
    {
        // Participants are deleted one by one so their stored files go too.
        DB::transaction(function () use ($cycle) {
            $cycle->participants->each->delete();
            $cycle->delete();
        });

        if ($cycle->template_path) {
            Storage::disk('local')->delete($cycle->template_path);
        }

        return $this->done(__('Survey cycle deleted successfully.'));
    }

    public function template(SurveyCycle $cycle): StreamedResponse
    {
        abort_unless($cycle->template_path && Storage::disk('local')->exists($cycle->template_path), 404);

        return Storage::disk('local')->download($cycle->template_path, $cycle->template_name);
    }

    /**
     * The standard job catalogue of a cycle (from its template).
     */
    public function jobs(Request $request): Response
    {
        $cycle = self::current($request);
        $query = BenchmarkJob::query()->where('survey_cycle_id', $cycle?->id)
            ->when($request->input('industry'), fn ($q, $industry) => $q->where('industry', $industry))
            ->when($request->input('job_family'), fn ($q, $family) => $q->where('job_family', $family));

        return Inertia::render('benchmark/jobs/index', [
            'jobs' => TableQuery::paginate($query, $request, ['code', 'title', 'job_family', 'summary'], ['code', 'title', 'job_family', 'industry'], 'code'),
            'filters' => TableQuery::filters($request, ['cycle', 'industry', 'job_family']),
            'cycles' => SurveyCycle::query()->latest('id')->get(['id', 'name']),
            'cycle' => $cycle?->only('id', 'name'),
            'industries' => $cycle?->lookup('IndustryList') ?? [],
            'families' => $cycle?->lookup('JobFamilyList') ?? [],
        ]);
    }

    /**
     * The cycle picked with ?cycle=, else the newest one.
     */
    public static function current(Request $request): ?SurveyCycle
    {
        return SurveyCycle::query()->when($request->integer('cycle'), fn ($q, $id) => $q->whereKey($id))->latest('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating, ?SurveyCycle $cycle = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('survey_cycles')->ignore($cycle)],
            'status' => ['required', Rule::in(SurveyCycle::STATUSES)],
            'min_companies' => ['required', 'integer', 'min:1', 'max:20'],
            'template' => [$creating ? 'required_without:files' : 'nullable', 'nullable', 'file', 'extensions:xlsx', 'max:20480'],
            'files' => ['nullable', 'array', 'max:50'],
            'files.*' => ['file', 'extensions:xlsx', 'max:10240'],
        ], attributes: ['files.*' => __('file')]);
        unset($data['template'], $data['files']);

        return $data;
    }

    /**
     * @return array{lookups: array<string, list<string>>, jobs: list<array<string, string|null>>}
     */
    private function readTemplate(UploadedFile $file, string $field = 'template', bool $blank = true): array
    {
        try {
            $template = SurveyWorkbook::readTemplate($file->getRealPath(), $blank);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        if ($template['jobs'] === [] || ($template['lookups']['JobLevelList'] ?? []) === []) {
            throw ValidationException::withMessages([$field => __('The workbook has no job catalogue or dropdown lists (Lookups sheet).')]);
        }

        return $template;
    }

    /**
     * @param  array{lookups: array<string, list<string>>, jobs: list<array<string, string|null>>}  $template
     */
    private function saveTemplate(SurveyCycle $cycle, ?UploadedFile $file, array $template): void
    {
        $old = $cycle->template_path;
        $cycle->update([
            'template_path' => $file ? ($file->store(self::TEMPLATE_DIRECTORY, 'local') ?: throw new \RuntimeException('Could not store the template.')) : $old,
            'template_name' => $file ? $file->getClientOriginalName() : $cycle->template_name,
            'lookups' => $template['lookups'],
        ]);

        $cycle->jobs()->delete();
        foreach (array_chunk($template['jobs'], 200) as $chunk) {
            BenchmarkJob::insert(array_map(fn (array $job) => [...$job, 'survey_cycle_id' => $cycle->id], $chunk));
        }

        if ($old && $file) {
            Storage::disk('local')->delete($old);
        }
    }

    /**
     * Clears remarks that are just the (newly uploaded) blank template's guidance text.
     */
    private function clearGuidanceRemarks(SurveyCycle $cycle): void
    {
        SurveyBenefit::query()
            ->whereIn('survey_participant_id', $cycle->participants()->select('id'))
            ->whereNotNull('remarks')
            ->get()
            ->filter(fn (SurveyBenefit $benefit) => SurveyWorkbook::isGuidance((string) $benefit->remarks, $cycle))
            ->each(fn (SurveyBenefit $benefit) => $benefit->update(['remarks' => null]));
    }
}
