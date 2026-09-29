<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomPageController extends Controller
{
    public function index(Request $request): Response
    {
        $pages = TableQuery::paginate(CustomPage::query()->select(['id', 'title', 'slug', 'content', 'is_active', 'sort_order', 'created_at']), $request, ['title', 'slug'], ['title', 'sort_order', 'created_at']);

        foreach ($pages->items() as $page) {
            $page->setAttribute('excerpt', Str::limit(trim(html_entity_decode(strip_tags((string) $page->content))), 50))->makeHidden('content');
        }

        return Inertia::render('landing-page/custom-pages/index', [
            'pages' => $pages,
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('landing-page/custom-pages/form', ['page' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomPage::create($this->validated($request));

        return $this->saved(__('Page created successfully.'));
    }

    public function edit(CustomPage $customPage): Response
    {
        return Inertia::render('landing-page/custom-pages/form', ['page' => $customPage]);
    }

    public function update(Request $request, CustomPage $customPage): RedirectResponse
    {
        $customPage->update($this->validated($request, $customPage));

        return $this->saved(__('Page updated successfully.'));
    }

    public function destroy(CustomPage $customPage): RedirectResponse
    {
        $customPage->delete();

        return $this->done(__('Page deleted successfully.'));
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('landing-page.custom-pages.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CustomPage $page = null): array
    {
        // A blank slug is generated from the title.
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title')))]);

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('custom_pages', 'slug')->ignore($page)],
            'content' => ['required', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]) + ['is_active' => false, 'sort_order' => 0];
    }
}
