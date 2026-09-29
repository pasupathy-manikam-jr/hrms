<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\JobLocation;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobLocationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = JobLocation::query()
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->filled('is_remote'), fn ($q) => $q->where('is_remote', $request->boolean('is_remote')));

        return Inertia::render('hr/recruitment/job-locations/index', [
            'jobLocations' => TableQuery::paginate($query, $request, ['name', 'city', 'state', 'country'], ['name', 'created_at']),
            'statusCounts' => $this->statusCounts($request),
            'filters' => TableQuery::filters($request, ['status', 'is_remote']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        JobLocation::create($this->validated($request));

        return $this->done(__('Job location created successfully.'));
    }

    public function update(Request $request, JobLocation $jobLocation): RedirectResponse
    {
        abort_unless($jobLocation->isVisibleTo($request->user()), 403);
        $jobLocation->update($this->validated($request));

        return $this->done(__('Job location updated successfully.'));
    }

    public function toggleStatus(Request $request, JobLocation $jobLocation): RedirectResponse
    {
        abort_unless($jobLocation->isVisibleTo($request->user()), 403);
        $jobLocation->update(['status' => $jobLocation->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Job location status updated.'));
    }

    public function destroy(Request $request, JobLocation $jobLocation): RedirectResponse
    {
        abort_unless($jobLocation->isVisibleTo($request->user()), 403);
        $jobLocation->delete();

        return $this->done(__('Job location deleted successfully.'));
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(Request $request): array
    {
        $counts = JobLocation::query()->visibleTo($request->user())->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['all' => (int) $counts->sum()] + collect(JobLocation::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_remote' => ['boolean'],
            'status' => ['required', Rule::in(JobLocation::STATUSES)],
        ]);
    }
}
