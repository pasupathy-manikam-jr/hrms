<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\JobCategory;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = JobCategory::query()
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('hr/recruitment/job-categories/index', [
            'jobCategories' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        JobCategory::create($this->validated($request));

        return $this->done(__('Job category created successfully.'));
    }

    public function update(Request $request, JobCategory $jobCategory): RedirectResponse
    {
        abort_unless($jobCategory->isVisibleTo($request->user()), 403);
        $jobCategory->update($this->validated($request));

        return $this->done(__('Job category updated successfully.'));
    }

    public function toggleStatus(Request $request, JobCategory $jobCategory): RedirectResponse
    {
        abort_unless($jobCategory->isVisibleTo($request->user()), 403);
        $jobCategory->update(['status' => $jobCategory->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Job category status updated.'));
    }

    public function destroy(Request $request, JobCategory $jobCategory): RedirectResponse
    {
        abort_unless($jobCategory->isVisibleTo($request->user()), 403);
        $jobCategory->delete();

        return $this->done(__('Job category deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(JobCategory::STATUSES)],
        ]);
    }
}
