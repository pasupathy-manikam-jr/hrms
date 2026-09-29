<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobCategory;
use App\Models\JobLocation;
use App\Models\JobPosting;
use App\Models\JobType;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobPostingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = JobPosting::query()
            ->visibleTo($request->user())
            ->with(['category:id,name', 'jobType:id,name', 'location:id,name', 'branch:id,name', 'department:id,name'])
            ->withCount('candidates')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->filled('is_published'), fn ($q) => $q->where('is_published', $request->boolean('is_published')))
            ->when($request->integer('job_type_id'), fn ($q, $id) => $q->where('job_type_id', $id));

        return Inertia::render('hr/recruitment/job-postings/index', [
            'jobPostings' => TableQuery::paginate($query, $request, ['title', 'job_code'], ['title', 'job_code', 'application_deadline', 'created_at']),
            'statusCounts' => $this->statusCounts($request),
            'jobCategories' => JobCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'jobTypes' => JobType::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => JobLocation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'branch_id']),
            'filters' => TableQuery::filters($request, ['status', 'is_published', 'job_type_id']),
        ]);
    }

    public function show(Request $request, JobPosting $jobPosting): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($jobPosting->isVisibleTo($user), 404);

        return Inertia::render('hr/recruitment/job-postings/show', [
            'jobPosting' => $jobPosting->load(['category:id,name', 'jobType:id,name', 'location:id,name', 'branch:id,name', 'department:id,name']),
            // Applicants are only listed for users who may see candidates (employees can browse postings but not applicants).
            'candidates' => $user->can('manage-candidates')
                ? $jobPosting->candidates()->visibleTo($user)->with('source:id,name')->latest('application_date')
                    ->get(['id', 'job_id', 'source_id', 'first_name', 'last_name', 'email', 'gender', 'experience_years', 'status', 'application_date'])
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        JobPosting::create($this->validated($request));

        return $this->done(__('Job posting created successfully.'));
    }

    public function update(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        abort_unless($jobPosting->isVisibleTo($request->user()), 403);
        $jobPosting->update($this->validated($request));

        return $this->done(__('Job posting updated successfully.'));
    }

    /**
     * Publish a draft (or closed) posting, or take a published one back to draft.
     */
    public function publish(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        abort_unless($jobPosting->isVisibleTo($request->user()), 403);
        $jobPosting->update(['status' => $jobPosting->is_published ? 'Draft' : 'Published']);

        return $this->done($jobPosting->is_published ? __('Job posting published.') : __('Job posting unpublished.'));
    }

    public function destroy(Request $request, JobPosting $jobPosting): RedirectResponse
    {
        abort_unless($jobPosting->isVisibleTo($request->user()), 403);
        $jobPosting->delete();

        return $this->done(__('Job posting deleted successfully.'));
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(Request $request): array
    {
        $counts = JobPosting::query()->visibleTo($request->user())->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['all' => (int) $counts->sum()] + collect(JobPosting::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'job_category_id' => ['nullable', 'integer', Rule::exists('job_categories', 'id')],
            'job_type_id' => ['required', 'integer', Rule::exists('job_types', 'id')],
            'location_id' => ['required', 'integer', Rule::exists('job_locations', 'id')],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('branch_id', $request->integer('branch_id'))],
            'positions' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_experience' => ['required', 'numeric', 'min:0', 'max:99'],
            'max_experience' => ['nullable', 'numeric', 'gte:min_experience', 'max:99'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'gte:min_salary'],
            'start_date' => ['nullable', 'date'],
            'application_deadline' => ['nullable', 'date'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'requirements' => ['nullable', 'string', 'max:10000'],
            'benefits' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(JobPosting::PRIORITIES)],
            'is_featured' => ['boolean'],
            'status' => ['required', Rule::in(JobPosting::STATUSES)],
        ]);

        // The form sends skills as one comma-separated string.
        $data['skills'] = array_values(array_filter(array_map('trim', explode(',', $data['skills'] ?? ''))));

        return $data;
    }
}
