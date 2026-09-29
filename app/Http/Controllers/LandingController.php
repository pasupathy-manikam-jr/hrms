<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\CustomPage;
use App\Models\LandingPageSetting;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * The public landing page, rendered from the saved Landing Page settings.
     */
    public function home(): Response|RedirectResponse
    {
        if (! Setting::get('landingPageEnabled')) {
            return redirect()->route('login');
        }

        return Inertia::render('welcome', [
            'landing' => LandingPageSetting::content(),
            'customPages' => CustomPage::links(),
        ]);
    }

    /**
     * An active custom page inside the landing header and footer; its HTML is allow-list sanitized.
     */
    public function page(string $slug): Response
    {
        $page = CustomPage::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $landing = LandingPageSetting::content();

        return Inertia::render('custom-page', [
            'page' => [
                'title' => $page->title,
                'content' => HtmlSanitizer::clean($page->content),
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ],
            'footer' => $landing['section_visibility']['footer'] ? $landing['sections']['footer'] : null,
            'customPages' => CustomPage::links(),
        ]);
    }

    /**
     * Store a message sent from the landing page contact form.
     */
    public function contact(Request $request): RedirectResponse
    {
        ContactMessage::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! Your message has been sent.')]);

        return back();
    }

    /**
     * Subscribe an email address to the newsletter.
     */
    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        NewsletterSubscriber::firstOrCreate($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks for subscribing!')]);

        return back();
    }
}
