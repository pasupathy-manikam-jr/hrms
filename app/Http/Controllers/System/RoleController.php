<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\TableQuery;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /** Roles whose permissions can't change, so nobody can lock the company out. */
    public const LOCKED = ['company'];

    public function index(Request $request): Response
    {
        $roles = TableQuery::paginate(Role::query()->withCount(['permissions', 'users']), $request, ['name', 'label', 'description'], ['name', 'label', 'created_at']);

        foreach ($roles->items() as $role) {
            $role->setAttribute('is_editable', self::isEditable($role))->setAttribute('is_deletable', self::isDeletable($role))
                ->setAttribute('permission_preview', $role->permissions()->orderBy('permissions.id')->limit(3)->pluck('label')->filter()->values());
        }

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/form', ['role' => null, 'permissions' => $this->permissionGroups()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $name = Str::slug($data['label']);

        if (Role::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['label' => __('A role with this name already exists.')]);
        }

        Role::create(['name' => $name, 'guard_name' => 'web', 'label' => $data['label'], 'description' => $data['description']])
            ->syncPermissions($data['permissions']);

        return $this->saved(__('Role created successfully.'));
    }

    public function show(Role $role): Response
    {
        return Inertia::render('roles/show', [
            'role' => [...$role->only('id', 'name', 'label', 'description', 'created_at'), 'is_editable' => self::isEditable($role)],
            'users' => $role->users()->orderBy('name')->get(['users.id', 'name', 'email', 'avatar_path', 'status']),
            'permissions' => $this->permissionGroups($role),
        ]);
    }

    public function edit(Role $role): Response
    {
        abort_unless(self::isEditable($role), 403, __('This role cannot be edited.'));

        return Inertia::render('roles/form', [
            'role' => [...$role->only('id', 'name', 'label', 'description'), 'permissions' => $role->permissions()->pluck('name')],
            'permissions' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless(self::isEditable($role), 403, __('This role cannot be edited.'));

        $data = $this->validated($request);
        $role->update(['label' => $data['label'], 'description' => $data['description']]);
        $role->syncPermissions($data['permissions']);

        return $this->saved(__('Role updated successfully.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (! self::isDeletable($role)) {
            return $this->toast('error', __('Built-in roles cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            return $this->toast('error', __('This role is assigned to users. Reassign them before deleting it.'));
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->done(__('Role deleted successfully.'));
    }

    public static function isEditable(Role $role): bool
    {
        return ! in_array($role->name, self::LOCKED, true);
    }

    public static function isDeletable(Role $role): bool
    {
        return ! array_key_exists($role->name, RolesSeeder::ROLES);
    }

    private function saved(string $message): RedirectResponse
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('roles.index');
    }

    /**
     * Every permission grouped by module label, e.g. ["Users" => [{name, label, description}, ...]].
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function permissionGroups(?Role $role = null): array
    {
        return ($role ? $role->permissions() : Permission::query())
            ->orderBy('permissions.id')->get(['permissions.id', 'module', 'name', 'label', 'description'])
            ->groupBy(fn (Permission $permission) => Str::headline($permission->getAttribute('module') ?? 'other'))
            ->sortKeys()
            ->map(fn ($group) => $group->values()->toArray())
            ->all();
    }

    /**
     * @return array{label: string, description: string|null, permissions: list<string>}
     */
    private function validated(Request $request): array
    {
        /** @var array{label: string, description: string|null, permissions?: list<string>} $data */
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        return ['label' => $data['label'], 'description' => $data['description'] ?? null, 'permissions' => $data['permissions'] ?? []];
    }
}
