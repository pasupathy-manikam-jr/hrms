<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\IpRestriction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the currency list and the demo's allowed IP addresses.
     */
    public function run(): void
    {
        $now = now();

        Currency::query()->insertOrIgnore(
            collect(File::json(database_path('demo/currencies.json'), JSON_THROW_ON_ERROR))
                ->map(fn (array $currency) => [...$currency, 'created_at' => $now, 'updated_at' => $now])
                ->all(),
        );

        foreach (['192.168.1.1', '192.168.1.100', '10.0.0.1', '172.16.0.1', '203.0.113.1'] as $ip) {
            IpRestriction::query()->firstOrCreate(['ip_address' => $ip]);
        }
    }
}
