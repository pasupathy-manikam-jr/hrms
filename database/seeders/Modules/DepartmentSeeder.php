<?php

namespace Database\Seeders\Modules;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DepartmentSeeder extends Seeder
{
    /**
     * Seed the demo's departments, matched to branches by name.
     */
    public function run(): void
    {
        $branches = Branch::query()->pluck('id', 'name');

        foreach (File::json(database_path('demo/departments.json'), JSON_THROW_ON_ERROR) as $department) {
            if (! $branchId = $branches[$department['branch']] ?? null) {
                continue;
            }

            Department::query()->firstOrCreate(
                ['name' => $department['name'], 'branch_id' => $branchId],
                ['description' => $department['description'], 'status' => $department['status']],
            );
        }
    }
}
