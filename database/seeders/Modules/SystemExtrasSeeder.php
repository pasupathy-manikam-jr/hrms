<?php

namespace Database\Seeders\Modules;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SystemExtrasSeeder extends Seeder
{
    /**
     * Seed the demo's email templates (one row per language). Existing translations are kept,
     * so edits made in the app survive a re-seed. Login history needs no seed: logins fill it.
     */
    public function run(): void
    {
        foreach (File::json(database_path('demo/email-templates.json'), JSON_THROW_ON_ERROR) as $row) {
            $template = EmailTemplate::query()->firstOrCreate(['name' => $row['name']], ['from' => $row['from']]);

            foreach ($row['langs'] as $lang => $translation) {
                $template->emailTemplateLangs()->firstOrCreate(['lang' => $lang], $translation);
            }
        }
    }
}
