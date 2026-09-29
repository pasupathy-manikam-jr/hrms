<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\ChecklistItem;
use App\Models\OnboardingChecklist;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistItemController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ChecklistItem::query()
            ->visibleTo($request->user())
            ->with('checklist:id,name')
            ->when($request->input('checklist_id'), fn ($q, $id) => $q->where('checklist_id', $id))
            ->when($request->input('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->filled('is_required'), fn ($q) => $q->where('is_required', $request->boolean('is_required')));

        return Inertia::render('hr/recruitment/checklist-items/index', [
            'checklistItems' => TableQuery::paginate($query, $request, ['task_name', 'description', 'assigned_to_role'], ['task_name', 'category', 'due_day', 'sort_order', 'created_at']),
            'checklists' => OnboardingChecklist::query()->orderBy('name')->get(['id', 'name']),
            'categories' => ChecklistItem::CATEGORIES,
            'filters' => TableQuery::filters($request, ['checklist_id', 'category', 'is_required']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ChecklistItem::create($this->validated($request));

        return $this->done(__('Checklist item created successfully.'));
    }

    public function update(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        abort_unless($checklistItem->isVisibleTo($request->user()), 403);
        $checklistItem->update($this->validated($request));

        return $this->done(__('Checklist item updated successfully.'));
    }

    public function toggleStatus(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        abort_unless($checklistItem->isVisibleTo($request->user()), 403);
        $checklistItem->update(['status' => $checklistItem->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Checklist item status updated.'));
    }

    public function destroy(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        abort_unless($checklistItem->isVisibleTo($request->user()), 403);
        $checklistItem->delete();

        return $this->done(__('Checklist item deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'checklist_id' => ['required', Rule::exists('onboarding_checklists', 'id')],
            'task_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(ChecklistItem::CATEGORIES)],
            'assigned_to_role' => ['nullable', 'string', 'max:255'],
            'due_day' => ['required', 'integer', 'min:0', 'max:365'],
            'is_required' => ['boolean'],
            'status' => ['sometimes', Rule::in(ChecklistItem::STATUSES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $data['sort_order'] ??= 0;

        return $data;
    }
}
