<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date']]);
        $user = $request->user();

        $query = Announcement::query()
            ->with(['departments:id,name,branch_id', 'branches:id,name'])
            ->visibleTo($user)
            ->when($request->input('category'), fn (Builder $q, $category) => $q->where('category', $category))
            ->when($request->integer('department_id'), fn (Builder $q, int $id) => $q->whereHas('departments', fn (Builder $q) => $q->whereKey($id)))
            ->when($request->integer('branch_id'), fn (Builder $q, int $id) => $q->whereHas('branches', fn (Builder $q) => $q->whereKey($id)))
            ->when(in_array($request->input('status'), Announcement::STATUSES, true), fn (Builder $q) => $q->withStatus($request->string('status')->toString()))
            ->when($request->input('priority') === 'high', fn (Builder $q) => $q->where('is_high_priority', true))
            ->when($request->input('priority') === 'normal', fn (Builder $q) => $q->where('is_high_priority', false))
            ->when($request->input('featured') === 'yes', fn (Builder $q) => $q->where('is_featured', true))
            ->when($request->input('featured') === 'no', fn (Builder $q) => $q->where('is_featured', false))
            ->when($request->date('date_from'), fn (Builder $q, $date) => $q->whereDate('start_date', '>=', $date))
            ->when($request->date('date_to'), fn (Builder $q, $date) => $q->whereDate('start_date', '<=', $date));

        return Inertia::render('hr/announcements/index', [
            'announcements' => TableQuery::paginate($query, $request, ['title', 'category', 'description'], ['title', 'start_date', 'created_at']),
            'departments' => Department::query()->with('branch:id,name')->orderBy('name')->get(['id', 'name', 'branch_id']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Announcement::CATEGORIES,
            'filters' => TableQuery::filters($request, ['category', 'department_id', 'branch_id', 'status', 'priority', 'featured', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Featured, high-priority, upcoming and all announcements the user may see, filtered like the demo.
     */
    public function dashboard(Request $request): Response
    {
        $all = Announcement::query()
            ->with(['departments:id,name', 'branches:id,name'])
            ->visibleTo($request->user())
            ->when($request->input('category'), fn (Builder $q, $category) => $q->where('category', $category))
            ->when($request->integer('department_id'), fn (Builder $q, int $id) => $q->whereHas('departments', fn (Builder $q) => $q->whereKey($id)))
            ->when($request->integer('branch_id'), fn (Builder $q, int $id) => $q->whereHas('branches', fn (Builder $q) => $q->whereKey($id)))
            ->latest('start_date')->latest('id')
            ->get();

        return Inertia::render('hr/announcements/dashboard', [
            'allAnnouncements' => $all,
            'featuredAnnouncements' => $all->where('is_featured', true)->values(),
            'highPriorityAnnouncements' => $all->where('is_high_priority', true)->values(),
            'upcomingAnnouncements' => $all->where('status', 'upcoming')->sortBy('start_date')->values(),
            'categories' => Announcement::CATEGORIES,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['category', 'department_id', 'branch_id']),
        ]);
    }

    /**
     * The announcement itself; opening it counts as a view for its statistics.
     */
    public function show(Request $request, Announcement $announcement): Response
    {
        $this->authorizeVisible($request, $announcement);
        $announcement->viewers()->syncWithoutDetaching([$request->user()->id]);

        return Inertia::render('hr/announcements/show', [
            'announcement' => $announcement->load(['departments:id,name,branch_id', 'departments.branch:id,name', 'branches:id,name']),
            ...$this->engagement($announcement),
        ]);
    }

    public function statistics(Request $request, Announcement $announcement): Response
    {
        $this->authorizeVisible($request, $announcement);

        return Inertia::render('hr/announcements/statistics', [
            'announcement' => $announcement->load(['departments:id,name', 'branches:id,name']),
            ...$this->engagement($announcement),
        ]);
    }

    public function document(Request $request, Announcement $announcement): StreamedResponse
    {
        $this->authorizeVisible($request, $announcement);

        return $announcement->downloadUpload();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $announcement = (new Announcement([...$data, 'created_by' => $request->user()?->id]))->attachUploadFrom($request);
            $announcement->save();
            $this->syncTargets($announcement, $data);
        });

        return $this->done(__('Announcement created successfully.'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $announcement, $request) {
            $announcement->fill($data)->attachUploadFrom($request)->save();
            $this->syncTargets($announcement, $data);
        });

        return $this->done(__('Announcement updated successfully.'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return $this->done(__('Announcement deleted successfully.'));
    }

    private function authorizeVisible(Request $request, Announcement $announcement): void
    {
        abort_unless(Announcement::query()->visibleTo($request->user())->whereKey($announcement->id)->exists(), 404);
    }

    /**
     * Audience size, how many of it have opened the announcement, and the read rate.
     *
     * @return array{totalEmployees: int, viewCount: int, viewPercentage: int}
     */
    private function engagement(Announcement $announcement): array
    {
        $total = $announcement->audience()->count();
        $viewed = $announcement->audience()->whereIn('user_id', $announcement->viewers()->select('users.id'))->count();

        return [
            'totalEmployees' => $total,
            'viewCount' => $viewed,
            'viewPercentage' => $total > 0 ? (int) round($viewed / $total * 100) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTargets(Announcement $announcement, array $data): void
    {
        $companyWide = (bool) ($data['is_company_wide'] ?? false);
        $announcement->departments()->sync($companyWide ? [] : ($data['department_ids'] ?? []));
        $announcement->branches()->sync($companyWide ? [] : ($data['branch_ids'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Announcement::CATEGORIES)],
            'description' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:65000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_featured' => ['boolean'],
            'is_high_priority' => ['boolean'],
            'is_company_wide' => ['boolean'],
            'department_ids' => ['array', 'required_if_declined:is_company_wide'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'document' => Announcement::uploadRules(),
        ]);
        unset($data['document']);

        return $data;
    }
}
