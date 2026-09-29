<?php

namespace Database\Seeders\Modules;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetType;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class AssetSeeder extends Seeder
{
    /**
     * Seed the demo's asset data. Add further asset modules as their own seed*() steps.
     */
    public function run(): void
    {
        $this->seedAssetTypes();
        $this->seedAssets();
        $this->seedMaintenances();
    }

    /**
     * Scheduled maintenance jobs; assets are matched by name.
     */
    private function seedMaintenances(): void
    {
        $assets = Asset::query()->pluck('id', 'name');

        foreach (File::json(database_path('demo/asset-maintenances.json'), JSON_THROW_ON_ERROR) as $row) {
            if ($assetId = $assets[$row['asset']] ?? null) {
                AssetMaintenance::query()->firstOrCreate(
                    ['asset_id' => $assetId, 'start_date' => $row['start_date']],
                    [...Arr::except($row, 'asset'), 'asset_id' => $assetId],
                );
            }
        }
    }

    private function seedAssetTypes(): void
    {
        foreach (File::json(database_path('demo/asset-types.json'), JSON_THROW_ON_ERROR) as $assetType) {
            AssetType::query()->firstOrCreate(['name' => $assetType['name']], $assetType);
        }
    }

    /**
     * Assets and their assignment history; types and employees are matched by name.
     */
    private function seedAssets(): void
    {
        $types = AssetType::query()->pluck('id', 'name');
        $employees = Employee::query()->join('users', 'users.id', '=', 'employees.user_id')->pluck('employees.id', 'users.name');

        foreach (File::json(database_path('demo/assets.json'), JSON_THROW_ON_ERROR) as $row) {
            $asset = Asset::query()->firstOrCreate(['asset_code' => $row['asset_code']], [
                ...Arr::except($row, ['asset_type', 'assignments']),
                'asset_type_id' => $types[$row['asset_type']] ?? null,
            ]);

            foreach ($row['assignments'] as $assignment) {
                if ($employeeId = $employees[$assignment['employee']] ?? null) {
                    $asset->assignments()->firstOrCreate(
                        ['employee_id' => $employeeId, 'assigned_at' => $assignment['assigned_at']],
                        Arr::only($assignment, ['returned_at', 'notes']),
                    );
                }
            }
        }
    }
}
