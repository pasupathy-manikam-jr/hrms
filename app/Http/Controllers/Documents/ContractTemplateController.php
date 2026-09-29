<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\ContractType;
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

class ContractTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ContractTemplate::query()
            ->visibleTo($request->user())
            ->with('contractType:id,name')
            ->when($request->integer('contract_type_id'), fn ($q, $id) => $q->where('contract_type_id', $id));

        $counts = TableQuery::countBy($query, 'status');
        $query->when($request->input('type') === 'default', fn ($q) => $q->where('is_default', true))
            ->when($request->input('type') === 'custom', fn ($q) => $q->where('is_default', false));
        $query->when(in_array($request->input('status'), ContractTemplate::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/contracts/contract-templates/index', [
            'contractTemplates' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'contractTypes' => ContractType::query()->orderBy('name')->get(['id', 'name']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(ContractTemplate::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['type', 'status', 'contract_type_id']),
        ]);
    }

    public function show(Request $request, ContractTemplate $contractTemplate): Response
    {
        abort_unless($contractTemplate->isVisibleTo($request->user()), 404);

        return Inertia::render('hr/contracts/contract-templates/show', [
            'template' => $contractTemplate->load('contractType:id,name'),
            'placeholders' => TemplateRenderer::placeholders($contractTemplate->template_content),
            'employees' => TemplateRenderer::employeeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ContractTemplate::create($this->validated($request));

        return $this->done(__('Contract template created successfully.'));
    }

    public function update(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        abort_unless($contractTemplate->isVisibleTo($request->user()), 403);
        $contractTemplate->update($this->validated($request));

        return $this->done(__('Contract template updated successfully.'));
    }

    /**
     * The lock action: switch the template on or off.
     */
    public function toggleStatus(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        abort_unless($contractTemplate->isVisibleTo($request->user()), 403);
        $contractTemplate->update(['status' => $contractTemplate->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Template status updated.'));
    }

    /**
     * The template (with its placeholders) as a one-page PDF.
     */
    public function download(Request $request, ContractTemplate $contractTemplate): HttpResponse
    {
        abort_unless($contractTemplate->isVisibleTo($request->user()), 404);
        // ponytail: SimplePdf is one page of Helvetica; switch to a PDF library if templates outgrow a page.
        $pdf = SimplePdf::make([$contractTemplate->name, ...explode("\n", str_replace("\r", '', $contractTemplate->template_content))]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str($contractTemplate->name)->slug().'.pdf"',
        ]);
    }

    public function destroy(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        abort_unless($contractTemplate->isVisibleTo($request->user()), 403);
        $contractTemplate->delete();

        return $this->done(__('Contract template deleted successfully.'));
    }

    /**
     * The template filled in for one employee.
     */
    public function preview(Request $request, ContractTemplate $contractTemplate): JsonResponse
    {
        abort_unless($contractTemplate->isVisibleTo($request->user()), 403);
        $request->validate(['employee_id' => ['required', 'integer']]);
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        return response()->json(['content' => TemplateRenderer::render($contractTemplate->template_content, TemplateRenderer::employeeValues($employee))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contract_type_id' => ['nullable', 'integer', Rule::exists('contract_types', 'id')],
            'template_content' => ['required', 'string', 'max:65000'],
            'is_default' => ['boolean'],
            'status' => ['required', Rule::in(ContractTemplate::STATUSES)],
        ]);
    }
}
