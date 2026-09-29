<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\OfferTemplate;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OfferTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = OfferTemplate::query()
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        $counts = OfferTemplate::query()->visibleTo($request->user())->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('hr/recruitment/offer-templates/index', [
            'offerTemplates' => TableQuery::paginate($query, $request, ['name', 'template_content'], ['name', 'created_at']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(OfferTemplate::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function show(Request $request, OfferTemplate $offerTemplate): Response
    {
        abort_unless($offerTemplate->isVisibleTo($request->user()), 404);

        return Inertia::render('hr/recruitment/offer-templates/show', [
            'offerTemplate' => $offerTemplate,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('hr/recruitment/offer-templates/form', ['offerTemplate' => null]);
    }

    public function edit(Request $request, OfferTemplate $offerTemplate): Response
    {
        abort_unless($offerTemplate->isVisibleTo($request->user()), 404);

        return Inertia::render('hr/recruitment/offer-templates/form', ['offerTemplate' => $offerTemplate]);
    }

    public function store(Request $request): RedirectResponse
    {
        OfferTemplate::create($this->validated($request));

        return $this->backToIndex(__('Offer template created successfully.'));
    }

    public function update(Request $request, OfferTemplate $offerTemplate): RedirectResponse
    {
        abort_unless($offerTemplate->isVisibleTo($request->user()), 403);
        $offerTemplate->update($this->validated($request));

        return $this->backToIndex(__('Offer template updated successfully.'));
    }

    /**
     * Add and Edit are full pages, so saving returns to the list rather than back to the form.
     */
    private function backToIndex(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('hr.recruitment.offer-templates.index');
    }

    public function toggleStatus(Request $request, OfferTemplate $offerTemplate): RedirectResponse
    {
        abort_unless($offerTemplate->isVisibleTo($request->user()), 403);
        $offerTemplate->update(['status' => $offerTemplate->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Offer template status updated.'));
    }

    public function destroy(Request $request, OfferTemplate $offerTemplate): RedirectResponse
    {
        abort_unless($offerTemplate->isVisibleTo($request->user()), 403);
        $offerTemplate->delete();

        return $this->done(__('Offer template deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_content' => ['required', 'string', 'max:20000'],
            'status' => ['required', Rule::in(OfferTemplate::STATUSES)],
        ]);
    }
}
