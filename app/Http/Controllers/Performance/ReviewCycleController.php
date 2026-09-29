<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\ReviewCycle;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewCycleController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ReviewCycle::query()
            ->visibleTo($request->user())
            ->when(in_array($request->input('status'), ReviewCycle::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')))
            ->when(in_array($request->input('frequency'), ReviewCycle::FREQUENCIES, true), fn ($q) => $q->where('frequency', $request->input('frequency')));

        return Inertia::render('hr/performance/review-cycles/index', [
            'reviewCycles' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'frequency', 'created_at']),
            'frequencies' => ReviewCycle::FREQUENCIES,
            'filters' => TableQuery::filters($request, ['status', 'frequency']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ReviewCycle::create($this->validated($request));

        return $this->done(__('Review cycle created successfully.'));
    }

    public function update(Request $request, ReviewCycle $reviewCycle): RedirectResponse
    {
        abort_unless($reviewCycle->isVisibleTo($request->user()), 403);
        $reviewCycle->update($this->validated($request));

        return $this->done(__('Review cycle updated successfully.'));
    }

    public function destroy(Request $request, ReviewCycle $reviewCycle): RedirectResponse
    {
        abort_unless($reviewCycle->isVisibleTo($request->user()), 403);
        $reviewCycle->delete();

        return $this->done(__('Review cycle deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(ReviewCycle::FREQUENCIES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(ReviewCycle::STATUSES)],
        ]);
    }
}
