<?php

use Database\Seeders\Modules\EngagementSurveySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Deploys only run migrations, so existing installs get the engagement permissions (granted per
     * database/demo/roles.json, without touching any other permission) and the default question bank here.
     */
    public function up(): void
    {
        $permissions = collect(File::json(database_path('demo/permissions.json'), JSON_THROW_ON_ERROR))
            ->where('module', 'engagement-survey');

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['module' => $permission['module'], 'label' => $permission['label'], 'description' => $permission['description']],
            );
        }

        foreach (File::json(database_path('demo/roles.json'), JSON_THROW_ON_ERROR) as $roleName => $granted) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo(array_values(array_intersect($granted, $permissions->pluck('name')->all())));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        EngagementSurveySeeder::seedDefaults();
    }

    public function down(): void
    {
        Permission::query()->where('module', 'engagement-survey')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
