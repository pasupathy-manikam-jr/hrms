<?php

namespace Database\Seeders\Modules;

use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class BranchSeeder extends Seeder
{
    /**
     * Seed the demo's branches.
     */
    public function run(): void
    {
        foreach (File::json(database_path('demo/branches.json'), JSON_THROW_ON_ERROR) as $branch) {
            Branch::query()->firstOrCreate(['name' => $branch['name']], $branch);
        }
    }
}
