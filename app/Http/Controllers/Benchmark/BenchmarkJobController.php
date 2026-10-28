<?php

namespace App\Http\Controllers\Benchmark;

use App\Http\Controllers\Controller;
use App\Models\Benchmark\BenchmarkJob;
use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use App\Models\Benchmark\SurveySalaryRow;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A cycle's Job Catalogue: the standard titles companies map their roles to. Submissions match their salary rows to
 * it by title, so changes carry through to those rows and the submissions are re-checked.
 */
class BenchmarkJobController extends Controller
{
    public function index(Request $request): Response
    {
        $cycle = SurveyCycleController::current($request);
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
            'levels' => $cycle?->lookup('JobLevelList') ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cycle = SurveyCycle::findOrFail($request->integer('survey_cycle_id'));
        $job = $cycle->jobs()->create($this->validated($request, $cycle));
        $this->linkRows($cycle, $job, $job->title, $job->code);

        return $this->done(__('Job added successfully.'));
    }

    public function update(Request $request, BenchmarkJob $job): RedirectResponse
    {
        $cycle = SurveyCycle::findOrFail($job->survey_cycle_id);
        [$title, $code] = [$job->title, $job->code];
        $job->update($this->validated($request, $cycle, $job));
        $this->linkRows($cycle, $job, $title, $code);

        return $this->done(__('Job updated successfully.'));
    }

    public function destroy(BenchmarkJob $job): RedirectResponse
    {
        $companies = SurveyParticipant::query()->where('survey_cycle_id', $job->survey_cycle_id)
            ->whereHas('salaryRows', fn ($q) => $q->where('job_code', $job->code))->count();

        if ($companies > 0) {
            return $this->toast('error', __('":title" is used by :n submissions in this cycle, so it can\'t be deleted. Rename it instead.', ['title' => $job->title, 'n' => $companies]));
        }

        $job->delete();

        return $this->done(__('Job deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, SurveyCycle $cycle, ?BenchmarkJob $job = null): array
    {
        $inCycle = fn (string $column) => Rule::unique('benchmark_jobs', $column)->where('survey_cycle_id', $cycle->id)->ignore($job);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', $inCycle('code')],
            'title' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:255'],
            'job_family' => ['required', 'string', 'max:255'],
            'typical_level' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'masco_group' => ['nullable', 'string', 'max:255'],
            'masco_reference' => ['nullable', 'string', 'max:255'],
        ]);

        // Rows are matched to the catalogue by title, ignoring case, so two jobs can't share one.
        $taken = $cycle->jobs()->whereRaw('lower(title) = ?', [mb_strtolower($data['title'])])
            ->when($job, fn ($q) => $q->whereKeyNot($job->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['title' => __('Another job in this cycle already has this title.')]);
        }

        return $data;
    }

    /**
     * Points the cycle's salary rows at the job: rows already linked to it (by its old or new code) or typed with
     * its old or new title take its title, code and family; their submissions are then re-checked.
     */
    private function linkRows(SurveyCycle $cycle, BenchmarkJob $job, string $previousTitle, string $previousCode): void
    {
        $titles = array_unique([mb_strtolower($previousTitle), mb_strtolower($job->title)]);
        $rows = SurveySalaryRow::query()
            ->whereIn('survey_participant_id', $cycle->participants()->select('id'))
            ->where(fn ($q) => $q->whereIn('job_code', array_unique([$previousCode, $job->code]))
                ->orWhereIn(DB::raw('lower(job_title)'), $titles));
        $participants = (clone $rows)->distinct()->pluck('survey_participant_id');

        $rows->update(['job_title' => $job->title, 'job_code' => $job->code, 'job_family' => $job->job_family]);
        SurveyParticipant::query()->whereKey($participants)->with('cycle')->get()->each->recheck();
    }
}
