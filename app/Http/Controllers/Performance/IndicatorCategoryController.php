<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\PerformanceIndicatorCategory;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IndicatorCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PerformanceIndicatorCategory::query()->visibleTo($request->user());
        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), PerformanceIndicatorCategory::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/performance/indicator-categories/index', [
            'categories' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'statusCounts' => ['all' => (int) $counts->sum(), 'active' => (int) ($counts['active'] ?? 0), 'inactive' => (int) ($counts['inactive'] ?? 0)],
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PerformanceIndicatorCategory::create($this->validated($request));

        return $this->done(__('Indicator category created successfully.'));
    }

    public function update(Request $request, PerformanceIndicatorCategory $indicatorCategory): RedirectResponse
    {
        abort_unless($indicatorCategory->isVisibleTo($request->user()), 403);
        $indicatorCategory->update($this->validated($request));

        return $this->done(__('Indicator category updated successfully.'));
    }

    public function toggleStatus(Request $request, PerformanceIndicatorCategory $indicatorCategory): RedirectResponse
    {
        abort_unless($indicatorCategory->isVisibleTo($request->user()), 403);
        $indicatorCategory->update(['status' => $indicatorCategory->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Indicator category status updated.'));
    }

    public function destroy(Request $request, PerformanceIndicatorCategory $indicatorCategory): RedirectResponse
    {
        abort_unless($indicatorCategory->isVisibleTo($request->user()), 403);
        $indicatorCategory->delete();

        return $this->done(__('Indicator category deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(PerformanceIndicatorCategory::STATUSES)],
        ]);
    }
}
