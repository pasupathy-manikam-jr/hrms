<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\JobType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = JobType::query()
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('hr/recruitment/job-types/index', [
            'jobTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        JobType::create($this->validated($request));

        return $this->done(__('Job type created successfully.'));
    }

    public function update(Request $request, JobType $jobType): RedirectResponse
    {
        abort_unless($jobType->isVisibleTo($request->user()), 403);
        $jobType->update($this->validated($request));

        return $this->done(__('Job type updated successfully.'));
    }

    public function toggleStatus(Request $request, JobType $jobType): RedirectResponse
    {
        abort_unless($jobType->isVisibleTo($request->user()), 403);
        $jobType->update(['status' => $jobType->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Job type status updated.'));
    }

    public function destroy(Request $request, JobType $jobType): RedirectResponse
    {
        abort_unless($jobType->isVisibleTo($request->user()), 403);
        $jobType->delete();

        return $this->done(__('Job type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(JobType::STATUSES)],
        ]);
    }
}
