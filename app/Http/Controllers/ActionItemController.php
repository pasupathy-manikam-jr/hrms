<?php

namespace App\Http\Controllers;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActionItemController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = ActionItem::query()
            ->visibleTo($user)
            ->with(['meeting:id,title,meeting_date', 'assignee:id,name,email,avatar_path'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('priority'), fn ($q, $priority) => $q->where('priority', $priority))
            ->when($request->input('assigned_to'), fn ($q, $assignee) => $q->where('assigned_to', $assignee))
            ->when($request->input('meeting_id'), fn ($q, $meeting) => $q->where('meeting_id', $meeting));

        $counts = ActionItem::query()->visibleTo($user)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('meetings/action-items/index', [
            'actionItems' => TableQuery::paginate($query, $request, ['title', 'description'], ['title', 'due_date', 'progress_percentage', 'created_at'], 'due_date'),
            'filters' => TableQuery::filters($request, ['status', 'priority', 'assigned_to', 'meeting_id']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(ActionItem::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'meetings' => Meeting::query()->visibleTo($user)->latest('meeting_date')->get(['id', 'title', 'meeting_date']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ActionItem::create($this->validated($request));

        return $this->done(__('Action item created successfully.'));
    }

    public function update(Request $request, ActionItem $actionItem): RedirectResponse
    {
        $this->ensureVisible($request, $actionItem);
        $actionItem->update($this->validated($request));

        return $this->done(__('Action item updated successfully.'));
    }

    /**
     * The demo's "Update Progress" action: 100% completes the item, anything above 0 starts it.
     */
    public function progress(Request $request, ActionItem $actionItem): RedirectResponse
    {
        $this->ensureVisible($request, $actionItem);
        $data = $request->validate([
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = match (true) {
            $data['progress_percentage'] === 100 => 'Completed',
            $data['progress_percentage'] > 0 => 'In Progress',
            default => $actionItem->status === 'Completed' ? 'Not Started' : $actionItem->status,
        };

        $actionItem->update([
            ...$data,
            'status' => $status,
            'completed_date' => $status === 'Completed' ? ($actionItem->completed_date ?? now()->toDateString()) : null,
        ]);

        return $this->done(__('Progress updated successfully.'));
    }

    public function destroy(Request $request, ActionItem $actionItem): RedirectResponse
    {
        $this->ensureVisible($request, $actionItem);
        $actionItem->delete();

        return $this->done(__('Action item deleted successfully.'));
    }

    private function ensureVisible(Request $request, ActionItem $actionItem): void
    {
        abort_unless(ActionItem::query()->visibleTo($request->user())->whereKey($actionItem->id)->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'meeting_id' => ['required', 'integer', Rule::exists(Meeting::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'priority' => ['required', Rule::in(ActionItem::PRIORITIES)],
            'status' => ['required', Rule::in(ActionItem::STATUSES)],
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'completed_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $data['completed_date'] = $data['status'] === 'Completed' ? ($data['completed_date'] ?? now()->toDateString()) : null;

        return $data;
    }
}
