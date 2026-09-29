<?php

namespace Database\Seeders\Modules;

use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ShiftSeeder extends Seeder
{
    /**
     * Seed the demo's work shifts.
     */
    public function run(): void
    {
        foreach (File::json(database_path('demo/shifts.json'), JSON_THROW_ON_ERROR) as $shift) {
            Shift::query()->firstOrCreate(['name' => $shift['name']], $shift);
        }
    }
}
