<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\PerformanceIndicator;
use App\Models\PerformanceIndicatorCategory;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IndicatorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = PerformanceIndicator::query()
            ->visibleTo($request->user())
            ->with('category:id,name')
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id));
        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), PerformanceIndicator::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/performance/indicators/index', [
            'indicators' => TableQuery::paginate($query, $request, ['name', 'description', 'measurement_unit'], ['name', 'measurement_unit', 'status', 'created_at']),
            'categories' => PerformanceIndicatorCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'units' => PerformanceIndicator::UNITS,
            'statusCounts' => ['all' => (int) $counts->sum(), 'active' => (int) ($counts['active'] ?? 0), 'inactive' => (int) ($counts['inactive'] ?? 0)],
            'filters' => TableQuery::filters($request, ['status', 'category_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PerformanceIndicator::create($this->validated($request));

        return $this->done(__('Indicator created successfully.'));
    }

    public function update(Request $request, PerformanceIndicator $indicator): RedirectResponse
    {
        abort_unless($indicator->isVisibleTo($request->user()), 403);
        $indicator->update($this->validated($request));

        return $this->done(__('Indicator updated successfully.'));
    }

    public function toggleStatus(Request $request, PerformanceIndicator $indicator): RedirectResponse
    {
        abort_unless($indicator->isVisibleTo($request->user()), 403);
        $indicator->update(['status' => $indicator->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Indicator status updated.'));
    }

    public function destroy(Request $request, PerformanceIndicator $indicator): RedirectResponse
    {
        abort_unless($indicator->isVisibleTo($request->user()), 403);
        $indicator->delete();

        return $this->done(__('Indicator deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('performance_indicator_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'measurement_unit' => ['required', Rule::in(PerformanceIndicator::UNITS)],
            'target_value' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(PerformanceIndicator::STATUSES)],
        ]);
    }
}
