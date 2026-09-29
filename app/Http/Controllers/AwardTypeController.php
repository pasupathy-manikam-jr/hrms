<?php

namespace App\Http\Controllers;

use App\Models\AwardType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AwardTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AwardType::query()
            ->visibleTo($request->user())
            ->when(in_array($request->input('status'), AwardType::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/award-types/index', [
            'awardTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'status', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AwardType::create($this->validated($request));

        return $this->done(__('Award type created successfully.'));
    }

    public function update(Request $request, AwardType $awardType): RedirectResponse
    {
        abort_unless($awardType->isVisibleTo($request->user()), 403);
        $awardType->update($this->validated($request));

        return $this->done(__('Award type updated successfully.'));
    }

    public function toggleStatus(Request $request, AwardType $awardType): RedirectResponse
    {
        abort_unless($awardType->isVisibleTo($request->user()), 403);
        $awardType->update(['status' => $awardType->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Award type status updated.'));
    }

    public function destroy(Request $request, AwardType $awardType): RedirectResponse
    {
        abort_unless($awardType->isVisibleTo($request->user()), 403);
        $awardType->delete();

        return $this->done(__('Award type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(AwardType::STATUSES)],
        ]);
    }
}
