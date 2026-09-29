<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Models\HrDocument;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $query = HrDocument::query()
            ->visibleTo($user)
            ->with('category:id,name,color', 'uploader:id,name,email,avatar_path')
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), HrDocument::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/documents/hr-documents/index', [
            'hrDocuments' => TableQuery::paginate($query, $request, ['title', 'description', 'version'], ['title', 'version', 'effective_date', 'status', 'download_count', 'created_at']),
            'categories' => DocumentCategory::query()->orderBy('name')->get(['id', 'name']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(HrDocument::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'stats' => [
                'total' => (int) $counts->sum(),
                'published' => (int) ($counts['published'] ?? 0),
                'expiring_soon' => HrDocument::query()->visibleTo($user)->whereBetween('expiry_date', [today(), today()->addDays(30)])->count(),
                'needs_acknowledgment' => HrDocument::query()->visibleTo($user)->where('requires_acknowledgment', true)->count(),
            ],
            'uploadTypes' => HrDocument::UPLOAD_EXTENSIONS,
            'uploadMaxKb' => HrDocument::UPLOAD_MAX_KB,
            'filters' => TableQuery::filters($request, ['status', 'category_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $document = new HrDocument([
            ...$this->validated($request, true),
            'uploaded_by' => $request->user()?->id,
            'created_by' => $request->user()?->id,
        ]);
        $document->attachUpload($request->file('file'))->save();

        return $this->done(__('Document uploaded successfully.'));
    }

    public function update(Request $request, HrDocument $hrDocument): RedirectResponse
    {
        $hrDocument->fill($this->validated($request, false));

        if ($request->hasFile('file')) {
            $hrDocument->attachUpload($request->file('file'))->fill(['uploaded_by' => $request->user()?->id]);
        }

        $hrDocument->save();

        return $this->done(__('Document updated successfully.'));
    }

    /**
     * The demo's "Update Status" action.
     */
    public function changeStatus(Request $request, HrDocument $hrDocument): RedirectResponse
    {
        abort_unless(HrDocument::query()->visibleTo($this->user($request))->whereKey($hrDocument->id)->exists(), 404);
        $hrDocument->update($request->validate(['status' => ['required', Rule::in(HrDocument::STATUSES)]]));

        return $this->done(__('Document status updated successfully.'));
    }

    public function destroy(HrDocument $hrDocument): RedirectResponse
    {
        $hrDocument->delete();

        return $this->done(__('Document deleted successfully.'));
    }

    /**
     * Stream the private file to a user allowed to see the document.
     */
    public function download(Request $request, HrDocument $hrDocument): StreamedResponse
    {
        abort_unless($hrDocument->isVisibleTo($this->user($request)), 404);

        $response = $hrDocument->downloadUpload();
        $hrDocument->increment('download_count');

        return $response;
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $fileRequired): array
    {
        return Arr::except($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', Rule::exists('document_categories', 'id')],
            'version' => ['required', 'string', 'max:20'],
            'effective_date' => ['nullable', 'date_format:Y-m-d'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_date'],
            'requires_acknowledgment' => ['boolean'],
            'status' => ['required', Rule::in(HrDocument::STATUSES)],
            'file' => HrDocument::uploadRules($fileRequired),
        ]), 'file');
    }
}
