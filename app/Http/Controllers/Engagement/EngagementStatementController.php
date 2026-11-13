<?php

namespace App\Http\Controllers\Engagement;

use App\Http\Controllers\Controller;
use App\Models\Engagement\EngagementElement;
use App\Models\Engagement\EngagementStatement;
use App\Models\Engagement\EngagementTier;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The question bank. New campaigns ask every active statement; a statement that has been answered can
 * only be deactivated, never deleted, so past results stay intact.
 */
class EngagementStatementController extends Controller
{
    public function index(Request $request): Response
    {
        $element = $request->string('element')->toString();
        $query = EngagementStatement::query()->with('element:id,name')->withCount('answers')
            ->when($element === 'overall', fn ($q) => $q->whereNull('engagement_element_id'))
            ->when(ctype_digit($element), fn ($q) => $q->where('engagement_element_id', (int) $element));

        return Inertia::render('engagement/statements/index', [
            'statements' => TableQuery::paginate($query, $request, ['text'], ['text', 'sort_order', 'created_at'], 'sort_order'),
            'filters' => TableQuery::filters($request, ['element']),
            'elements' => EngagementElement::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'description']),
            'tiers' => EngagementTier::query()->orderByDesc('level')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EngagementStatement::create($this->validated($request));

        return $this->done(__('Statement created successfully.'));
    }

    public function update(Request $request, EngagementStatement $statement): RedirectResponse
    {
        $statement->update($this->validated($request));

        return $this->done(__('Statement updated successfully.'));
    }

    public function toggleStatus(EngagementStatement $statement): RedirectResponse
    {
        $statement->update(['is_active' => ! $statement->is_active]);

        return $this->done(__('Statement status updated.'));
    }

    public function destroy(EngagementStatement $statement): RedirectResponse
    {
        if ($statement->answers()->exists() || $statement->campaigns()->exists()) {
            return $this->toast('error', __('This statement is used by a campaign. Deactivate it instead.'));
        }

        $statement->delete();

        return $this->done(__('Statement deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'engagement_element_id' => ['nullable', Rule::exists('engagement_elements', 'id')],
            'text' => ['required', 'string', 'max:500'],
            'reverse_scored' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [...$validated, 'sort_order' => $validated['sort_order'] ?? 0];
    }
}
