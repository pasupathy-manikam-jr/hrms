<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Holiday;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'year' => ['nullable', 'integer']]);

        $query = Holiday::query()
            ->with('branches:id,name')
            ->visibleTo($request->user())
            ->when(in_array($request->input('category'), Holiday::CATEGORIES, true), fn (Builder $q) => $q->where('category', $request->input('category')))
            ->when($request->integer('branch_id'), fn (Builder $q, int $id) => $q->whereHas('branches', fn (Builder $q) => $q->whereKey($id)))
            ->when($request->date('date_from'), fn (Builder $q, $date) => $q->whereDate('end_date', '>=', $date))
            ->when($request->date('date_to'), fn (Builder $q, $date) => $q->whereDate('start_date', '<=', $date))
            ->when($request->integer('year'), fn (Builder $q, int $year) => $q->whereYear('start_date', $year));

        return Inertia::render('hr/holidays/index', [
            'holidays' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'start_date', 'category', 'created_at'], 'start_date'),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Holiday::CATEGORIES,
            'years' => Holiday::query()->pluck('start_date')->map(fn ($date) => $date->year)->unique()->sort()->values(),
            'filters' => TableQuery::filters($request, ['category', 'branch_id', 'date_from', 'date_to', 'year']),
        ]);
    }

    /**
     * Every occurrence (recurring ones repeat yearly) from the start of last year to the end of next
     * year; months are switched in the browser like the main calendar.
     */
    public function calendar(Request $request): Response
    {
        $from = today()->toImmutable()->subYear()->startOfYear();
        $to = today()->toImmutable()->addYear()->endOfYear();

        $query = Holiday::query()
            ->with('branches:id,name')
            ->visibleTo($request->user())
            ->forBranch($request->integer('branch_id') ?: null)
            ->when(in_array($request->input('category'), Holiday::CATEGORIES, true), fn (Builder $q) => $q->where('category', $request->input('category')));

        $events = array_map(fn (array $o) => [
            'id' => "{$o['holiday']->id}_{$o['start']->year}",
            'title' => $o['holiday']->name,
            'start' => $o['start']->toDateString(),
            'end' => $o['end']->toDateString(),
            'category' => $o['holiday']->category,
            'description' => $o['holiday']->description,
            'is_paid' => $o['holiday']->is_paid,
            'is_half_day' => $o['holiday']->is_half_day,
            'is_recurring' => $o['holiday']->is_recurring,
            'branches' => $o['holiday']->branches->pluck('name'),
        ], Holiday::occurrences($query, $from, $to));

        return Inertia::render('hr/holidays/calendar', [
            'calendarEvents' => $events,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Holiday::CATEGORIES,
            'filters' => $request->only(['category', 'branch_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(fn () => Holiday::create($data)->branches()->sync($data['branch_ids']));

        return $this->done(__('Holiday created successfully.'));
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        abort_unless($holiday->isVisibleTo($request->user()), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($holiday, $data) {
            $holiday->update($data);
            $holiday->branches()->sync($data['branch_ids']);
        });

        return $this->done(__('Holiday updated successfully.'));
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        abort_unless($holiday->isVisibleTo($request->user()), 403);
        $holiday->delete();

        return $this->done(__('Holiday deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'category' => ['required', Rule::in(Holiday::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_paid' => ['boolean'],
            'is_half_day' => ['boolean'],
            'is_recurring' => ['boolean'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ]);
    }
}
