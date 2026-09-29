<?php

namespace Database\Seeders\Modules;

use App\Models\Department;
use App\Models\Designation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DesignationSeeder extends Seeder
{
    /**
     * Seed the demo's designations, matched to departments by department and branch name.
     */
    public function run(): void
    {
        $departments = Department::query()->with('branch:id,name')->get()
            ->keyBy(fn (Department $department) => $department->name.'|'.$department->branch->name);

        foreach (File::json(database_path('demo/designations.json'), JSON_THROW_ON_ERROR) as $designation) {
            if (! $department = $departments[$designation['department'].'|'.$designation['branch']] ?? null) {
                continue;
            }

            Designation::query()->firstOrCreate(
                ['name' => $designation['name'], 'department_id' => $department->id],
                ['description' => $designation['description'], 'status' => $designation['status']],
            );
        }
    }
}
