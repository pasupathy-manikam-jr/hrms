<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Support\SimplePdf;
use App\Support\TableQuery;
use App\Support\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DocumentTemplate::query()
            ->visibleTo($request->user())
            ->with('category:id,name,color')
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id));

        $counts = TableQuery::countBy($query, 'status');
        $query->when($request->input('type') === 'default', fn ($q) => $q->where('is_default', true))
            ->when($request->input('type') === 'custom', fn ($q) => $q->where('is_default', false));
        $query->when(in_array($request->input('status'), DocumentTemplate::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/documents/document-templates/index', [
            'documentTemplates' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'categories' => DocumentCategory::query()->orderBy('name')->get(['id', 'name']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(DocumentTemplate::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['type', 'status', 'category_id']),
        ]);
    }

    public function show(Request $request, DocumentTemplate $documentTemplate): Response
    {
        abort_unless($documentTemplate->isVisibleTo($request->user()), 404);

        return Inertia::render('hr/documents/document-templates/show', [
            'template' => $documentTemplate->load('category:id,name,color'),
            'placeholders' => TemplateRenderer::placeholders($documentTemplate->template_content),
            'employees' => TemplateRenderer::employeeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentTemplate::create($this->validated($request));

        return $this->done(__('Document template created successfully.'));
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        abort_unless($documentTemplate->isVisibleTo($request->user()), 403);
        $documentTemplate->update($this->validated($request));

        return $this->done(__('Document template updated successfully.'));
    }

    /**
     * The lock action: switch the template on or off.
     */
    public function toggleStatus(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        abort_unless($documentTemplate->isVisibleTo($request->user()), 403);
        $documentTemplate->update(['status' => $documentTemplate->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Template status updated.'));
    }

    /**
     * The template (with its placeholders) as a one-page PDF.
     */
    public function download(Request $request, DocumentTemplate $documentTemplate): HttpResponse
    {
        abort_unless($documentTemplate->isVisibleTo($request->user()), 404);
        // ponytail: SimplePdf is one page of Helvetica; switch to a PDF library if templates outgrow a page.
        $pdf = SimplePdf::make([$documentTemplate->name, ...explode("\n", str_replace("\r", '', $documentTemplate->template_content))]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str($documentTemplate->name)->slug().'.pdf"',
        ]);
    }

    public function destroy(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        abort_unless($documentTemplate->isVisibleTo($request->user()), 403);
        $documentTemplate->delete();

        return $this->done(__('Document template deleted successfully.'));
    }

    /**
     * The template filled in for one employee.
     */
    public function preview(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        abort_unless($documentTemplate->isVisibleTo($request->user()), 403);
        $request->validate(['employee_id' => ['required', 'integer']]);
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        return response()->json(['content' => TemplateRenderer::render($documentTemplate->template_content, TemplateRenderer::employeeValues($employee))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')],
            'template_content' => ['required', 'string', 'max:65000'],
            'is_default' => ['boolean'],
            'status' => ['required', Rule::in(DocumentTemplate::STATUSES)],
        ]);
    }
}
