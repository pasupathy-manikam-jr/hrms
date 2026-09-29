<?php

namespace Database\Seeders\Modules;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MalaysianNamesSeeder extends Seeder
{
    /**
     * Give the demo's people Malaysian names (Malay, Chinese, Indian, Sabahan, Sarawakian).
     * Runs last, after every seeder has matched people by the demo's original names/emails.
     * The demo logins (company@, hr@, employee@, manager@) keep their email addresses.
     */
    public function run(): void
    {
        /** @var list<array{from: string, name: string, email: string, gender: string}> $people */
        $people = File::json(database_path('demo/malaysian-names.json'), JSON_THROW_ON_ERROR);

        foreach ($people as $person) {
            $user = User::query()->whereIn('email', [$person['from'], $person['email']])->first();
            $user?->update(['name' => $person['name'], 'email' => $person['email']]);
            // Keep the recorded gender consistent with the new name (avatars use it).
            $user?->employee?->update(['gender' => $person['gender']]);
        }
    }
}
