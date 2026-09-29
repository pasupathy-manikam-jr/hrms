<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\GoalType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GoalTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = GoalType::query()->visibleTo($request->user());
        $query->when(in_array($request->input('status'), GoalType::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/performance/goal-types/index', [
            'goalTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        GoalType::create($this->validated($request));

        return $this->done(__('Goal type created successfully.'));
    }

    public function update(Request $request, GoalType $goalType): RedirectResponse
    {
        abort_unless($goalType->isVisibleTo($request->user()), 403);
        $goalType->update($this->validated($request));

        return $this->done(__('Goal type updated successfully.'));
    }

    public function destroy(Request $request, GoalType $goalType): RedirectResponse
    {
        abort_unless($goalType->isVisibleTo($request->user()), 403);
        $goalType->delete();

        return $this->done(__('Goal type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(GoalType::STATUSES)],
        ]);
    }
}
