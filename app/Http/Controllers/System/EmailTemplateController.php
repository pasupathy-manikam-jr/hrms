<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Support\TableQuery;
use App\Support\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EmailTemplate::query()
            ->withCount('emailTemplateLangs')
            ->with(['emailTemplateLangs' => fn ($q) => $q->where('lang', 'en')->select(['id', 'parent_id', 'lang', 'subject'])]);

        return Inertia::render('email-templates/index', [
            'templates' => TableQuery::paginate($query, $request, ['name', 'from'], ['name', 'updated_at'], 'name'),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function show(EmailTemplate $emailTemplate): Response
    {
        $emailTemplate->load('emailTemplateLangs');
        $english = $emailTemplate->emailTemplateLangs->firstWhere('lang', 'en');

        return Inertia::render('email-templates/show', [
            'template' => $emailTemplate,
            'languages' => collect(config()->array('app.locales'))->map(fn (array $locale, string $code) => ['code' => $code, 'name' => $locale[0]])->values(),
            'placeholders' => TemplateRenderer::placeholders(($english->subject ?? '').' '.($english->content ?? '')),
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $data = $request->validate([
            'lang' => ['required', Rule::in(array_keys(config('app.locales')))],
            'from' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:65000'],
        ]);

        $emailTemplate->update(['from' => $data['from']]);
        $emailTemplate->emailTemplateLangs()->updateOrCreate(['lang' => $data['lang']], $data);

        return $this->done(__('Email template updated successfully.'));
    }

    /**
     * Render unsaved subject/content with sample values so the editor can show what recipients get.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:65000'],
        ]);
        $subject = $data['subject'] ?? '';
        $content = $data['content'] ?? '';

        $values = collect(TemplateRenderer::placeholders($subject.' '.$content))
            ->mapWithKeys(fn (string $key) => [$key => '['.Str::headline($key).']'])
            ->merge(['app_name' => config('app.name'), 'app_url' => url('/')])
            ->all();

        return response()->json([
            'subject' => html_entity_decode(TemplateRenderer::render($subject, $values), ENT_QUOTES),
            'content' => TemplateRenderer::render($content, $values),
        ]);
    }
}
