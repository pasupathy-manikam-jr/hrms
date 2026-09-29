<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\JobLocation;
use App\Models\JobPosting;
use App\Models\JobType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public careers site (the demo's /{company}/career): published jobs, a job page and an application form
 * that files the applicant as a New candidate from the "Company Website" source.
 */
class CareerController extends Controller
{
    /** The demo's "Vacancies" filter buckets, as [min, max] positions. */
    public const VACANCY_RANGES = ['1-5' => [1, 5], '6-15' => [6, 15], '16-25' => [16, 25], '25+' => [26, null]];

    /** The demo's "Salary Range" filter buckets, on the job's minimum salary. */
    public const SALARY_RANGES = ['0-50000' => [0, 50000], '50000-100000' => [50000, 100000], '100000+' => [100000, null]];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer'],
            'salary' => ['nullable', Rule::in(array_keys(self::SALARY_RANGES))],
            'job_types' => ['nullable', 'array'],
            'job_types.*' => ['integer'],
            'vacancies' => ['nullable', 'array'],
            'vacancies.*' => [Rule::in(array_keys(self::VACANCY_RANGES))],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'salary'])],
        ]);

        $jobs = $this->published()
            ->with('jobType:id,name', 'location:id,name', 'branch:id,name')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->when($filters['job_types'] ?? null, fn ($q, $ids) => $q->whereIn('job_type_id', $ids))
            ->when($filters['salary'] ?? null, fn ($q, $key) => $this->between($q, 'min_salary', self::SALARY_RANGES[$key]))
            ->when($filters['vacancies'] ?? null, fn ($q, $keys) => $q->where(function (Builder $q) use ($keys) {
                foreach ($keys as $key) {
                    $q->orWhere(fn ($q) => $this->between($q, 'positions', self::VACANCY_RANGES[$key]));
                }
            }))
            ->when(
                $filters['sort'] ?? 'newest',
                fn ($q, $sort) => match ($sort) {
                    'oldest' => $q->orderBy('publish_date')->orderBy('id'),
                    'salary' => $q->orderByDesc('max_salary')->orderByDesc('id'),
                    default => $q->orderByDesc('is_featured')->orderByDesc('publish_date')->orderByDesc('id'),
                },
            )
            ->paginate(9)
            ->withQueryString();

        return Inertia::render('career/index', [
            'jobPostings' => $jobs,
            'jobTypes' => JobType::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => JobLocation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'salaryRanges' => array_keys(self::SALARY_RANGES),
            'vacancyRanges' => array_keys(self::VACANCY_RANGES),
            'company' => $this->company(),
            'filters' => (object) $filters,
        ]);
    }

    public function show(JobPosting $jobPosting): Response
    {
        abort_unless($this->published()->whereKey($jobPosting->id)->exists(), 404);

        return Inertia::render('career/show', [
            'job' => $jobPosting->load('jobType:id,name', 'location:id,name', 'branch:id,name', 'department:id,name'),
            'similarJobs' => $this->published()
                ->with('location:id,name', 'branch:id,name')
                ->whereKeyNot($jobPosting->id)
                ->orderByRaw('job_category_id = ? desc', [$jobPosting->job_category_id])
                ->latest('publish_date')
                ->limit(4)
                ->get(['id', 'job_code', 'title', 'location_id', 'branch_id', 'positions']),
            'company' => $this->company(),
        ]);
    }

    public function apply(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        abort_unless($this->published()->whereKey($jobPosting->id)->exists(), 404);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(Candidate::GENDERS)],
            'experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'current_position' => ['nullable', 'string', 'max:255'],
            'expected_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
        ]);

        if (Candidate::query()->where('job_id', $jobPosting->id)->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => __('You have already applied for this job.')]);
        }

        Candidate::create([
            ...$data,
            'job_id' => $jobPosting->id,
            'source_id' => CandidateSource::query()->where('name', 'Company Website')->value('id'),
            'status' => 'New',
            'application_date' => today()->toDateString(),
            // Owned by whoever posted the job, so "own candidates" recruiters see their applicants.
            'created_by' => $jobPosting->created_by,
        ]);

        return $this->done(__('Thank you! Your application has been submitted.'));
    }

    /**
     * @return Builder<JobPosting>
     */
    private function published(): Builder
    {
        return JobPosting::query()->where('status', 'Published')->where('is_published', true);
    }

    /**
     * @param  Builder<JobPosting>  $query
     * @param  array{0: int, 1: int|null}  $range
     * @return Builder<JobPosting>
     */
    private function between(Builder $query, string $column, array $range): Builder
    {
        return $query->where($column, '>=', $range[0])->when($range[1] !== null, fn ($q) => $q->where($column, '<=', $range[1]));
    }

    /**
     * @return array{name: string, email: string|null}
     */
    private function company(): array
    {
        return [
            'name' => (string) config('app.name'),
            'email' => User::query()->whereRelation('roles', 'name', 'company')->value('email'),
        ];
    }
}
