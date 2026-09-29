<?php

namespace Database\Seeders\Modules;

use App\Models\ContactMessage;
use App\Models\CustomPage;
use App\Models\LandingPageSetting;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LandingContentSeeder extends Seeder
{
    /**
     * Seed the landing page content, the demo's custom pages, contact inquiries and newsletter subscribers.
     */
    public function run(): void
    {
        if (! LandingPageSetting::query()->exists()) {
            LandingPageSetting::store(LandingPageSetting::defaults());
        }

        foreach (File::json(database_path('demo/custom-pages.json'), JSON_THROW_ON_ERROR) as $page) {
            CustomPage::query()->firstOrCreate(['slug' => $page['slug']], $page);
        }

        foreach (File::json(database_path('demo/contact-messages.json'), JSON_THROW_ON_ERROR) as $message) {
            ContactMessage::query()->firstOrCreate(['email' => $message['email'], 'subject' => $message['subject']], $message);
        }

        foreach (File::json(database_path('demo/newsletter-subscribers.json'), JSON_THROW_ON_ERROR) as $email) {
            NewsletterSubscriber::query()->firstOrCreate(['email' => $email]);
        }
    }
}
