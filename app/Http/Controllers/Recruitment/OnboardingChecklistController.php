<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\OnboardingChecklist;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingChecklistController extends Controller
{
    public function index(Request $request): Response
    {
        $query = OnboardingChecklist::query()
            ->visibleTo($request->user())
            ->when($request->filled('is_default'), fn ($q) => $q->where('is_default', $request->boolean('is_default')));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), OnboardingChecklist::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/recruitment/onboarding-checklists/index', [
            'onboardingChecklists' => TableQuery::paginate($query->withCount('items as checklist_items_count'), $request, ['name', 'description'], ['name', 'created_at']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(OnboardingChecklist::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'is_default']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        OnboardingChecklist::create($this->validated($request));

        return $this->done(__('Onboarding checklist created successfully.'));
    }

    public function update(Request $request, OnboardingChecklist $onboardingChecklist): RedirectResponse
    {
        abort_unless($onboardingChecklist->isVisibleTo($request->user()), 403);
        $onboardingChecklist->update($this->validated($request));

        return $this->done(__('Onboarding checklist updated successfully.'));
    }

    public function toggleStatus(Request $request, OnboardingChecklist $onboardingChecklist): RedirectResponse
    {
        abort_unless($onboardingChecklist->isVisibleTo($request->user()), 403);
        $onboardingChecklist->update(['status' => $onboardingChecklist->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Onboarding checklist status updated.'));
    }

    public function destroy(Request $request, OnboardingChecklist $onboardingChecklist): RedirectResponse
    {
        abort_unless($onboardingChecklist->isVisibleTo($request->user()), 403);
        $onboardingChecklist->delete();

        return $this->done(__('Onboarding checklist deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['boolean'],
            'status' => ['required', Rule::in(OnboardingChecklist::STATUSES)],
        ]);
    }
}
