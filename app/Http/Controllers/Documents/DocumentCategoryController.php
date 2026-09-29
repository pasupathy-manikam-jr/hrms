<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DocumentCategory::query()
            ->visibleTo($request->user())
            ->withCount('documents')
            ->when(in_array($request->input('status'), DocumentCategory::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')))
            ->when(in_array($request->input('is_mandatory'), ['yes', 'no'], true), fn ($q) => $q->where('is_mandatory', $request->input('is_mandatory') === 'yes'));

        return Inertia::render('hr/documents/document-categories/index', [
            'documentCategories' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request, ['status', 'is_mandatory']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentCategory::create($this->validated($request));

        return $this->done(__('Document category created successfully.'));
    }

    public function update(Request $request, DocumentCategory $documentCategory): RedirectResponse
    {
        abort_unless($documentCategory->isVisibleTo($request->user()), 403);
        $documentCategory->update($this->validated($request));

        return $this->done(__('Document category updated successfully.'));
    }

    /**
     * The lock action: switch the category on or off.
     */
    public function toggleStatus(Request $request, DocumentCategory $documentCategory): RedirectResponse
    {
        abort_unless($documentCategory->isVisibleTo($request->user()), 403);
        $documentCategory->update(['status' => $documentCategory->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Document category status updated.'));
    }

    public function destroy(Request $request, DocumentCategory $documentCategory): RedirectResponse
    {
        abort_unless($documentCategory->isVisibleTo($request->user()), 403);
        $documentCategory->delete();

        return $this->done(__('Document category deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'hex_color'],
            'icon' => ['sometimes', 'required', Rule::in(DocumentCategory::ICONS)],
            'is_mandatory' => ['boolean'],
            'status' => ['required', Rule::in(DocumentCategory::STATUSES)],
        ]);
    }
}
