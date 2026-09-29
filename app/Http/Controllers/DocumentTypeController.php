<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DocumentType::query()
            ->visibleTo($request->user())
            ->when(in_array($request->input('required'), ['yes', 'no'], true), fn ($q) => $q->where('is_required', $request->input('required') === 'yes'));

        return Inertia::render('hr/document-types/index', [
            'documentTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'is_required', 'created_at']),
            'filters' => TableQuery::filters($request, ['required']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentType::create($this->validated($request));

        return $this->done(__('Document type created successfully.'));
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        abort_unless($documentType->isVisibleTo($request->user()), 403);
        $documentType->update($this->validated($request));

        return $this->done(__('Document type updated successfully.'));
    }

    public function destroy(Request $request, DocumentType $documentType): RedirectResponse
    {
        abort_unless($documentType->isVisibleTo($request->user()), 403);
        $documentType->delete();

        return $this->done(__('Document type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
