<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /** Built-in roles and their labels; they can't be deleted (see RoleController). */
    public const ROLES = ['company' => 'Company', 'hr' => 'HR', 'manager' => 'Manager', 'employee' => 'Employee'];

    /**
     * Create the company, hr and employee roles with the WorkDo demo's permission sets,
     * and the demo's module, label and description for every permission.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var array<string, list<string>> $roles */
        $roles = File::json(database_path('demo/roles.json'), JSON_THROW_ON_ERROR);
        /** @var list<array{module: string|null, name: string, label: string|null, description: string|null}> $permissions */
        $permissions = File::json(database_path('demo/permissions.json'), JSON_THROW_ON_ERROR);

        $now = now();
        $rows = collect($permissions)->keyBy('name');

        // Any role permission the demo list lacks still gets a row.
        foreach (array_unique(array_merge(...array_values($roles))) as $name) {
            $rows[$name] ??= ['module' => null, 'name' => $name, 'label' => null, 'description' => null];
        }

        Permission::query()->upsert(
            $rows->values()->map(fn (array $row) => [...$row, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now])->all(),
            ['name', 'guard_name'],
            ['module', 'label', 'description', 'updated_at'],
        );

        Role::query()->upsert(
            collect(array_keys($roles))->map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'label' => $label = self::ROLES[$name] ?? Str::headline($name),
                'description' => $label.' Role',
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['name', 'guard_name'],
            ['label', 'description'],
        );

        $ids = Permission::query()->pluck('id', 'name');

        foreach (Role::query()->whereIn('name', array_keys($roles))->get() as $role) {
            $role->permissions()->sync($ids->only($roles[$role->name])->values());
        }

        // Model events are off while seeding, so Spatie won't flush its cache on its own.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
