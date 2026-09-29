<?php

namespace Database\Seeders\Modules;

use App\Models\AwardType;
use App\Models\Branch;
use App\Models\DocumentType;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class OrganizationExtrasSeeder extends Seeder
{
    /**
     * Seed the demo's holidays, award types and document types.
     */
    public function run(): void
    {
        $owner = User::query()->where('email', 'company@example.com')->value('id');
        $branchIds = Branch::query()->pluck('id', 'name');

        foreach (File::json(database_path('demo/holidays.json'), JSON_THROW_ON_ERROR) as $row) {
            $branches = $row['branches'];
            unset($row['branches']);

            Holiday::query()->firstOrCreate(['name' => $row['name']], $row + ['created_by' => $owner])
                ->branches()->syncWithoutDetaching($branchIds->only($branches)->values()->all());
        }

        foreach (File::json(database_path('demo/award-types.json'), JSON_THROW_ON_ERROR) as $row) {
            AwardType::query()->firstOrCreate(['name' => $row['name']], $row + ['created_by' => $owner]);
        }

        foreach (File::json(database_path('demo/document-types.json'), JSON_THROW_ON_ERROR) as $row) {
            DocumentType::query()->firstOrCreate(['name' => $row['name']], $row + ['created_by' => $owner]);
        }
    }
}
