<?php

namespace App\Http\Controllers\System;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Company-level users; employees (the `employee` role) are managed in the Employees module.
 */
class UserController extends Controller
{
    use PasswordValidationRules;

    public function index(Request $request): Response
    {
        $query = $this->companyUsers()->with('roles:id,name,label')
            ->when($request->filled('role'), fn (Builder $q) => $q->role($request->string('role')->toString()))
            ->when(in_array($request->input('status'), User::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status')));

        return Inertia::render('users/index', [
            'users' => TableQuery::paginate($query, $request, ['name', 'email'], ['name', 'email', 'created_at']),
            'roles' => $this->assignableRoles()->get(['id', 'name', 'label']),
            'filters' => TableQuery::filters($request, ['role', 'status']),
        ]);
    }

    public function show(User $user): Response
    {
        $this->ensureCompanyUser($user);

        return Inertia::render('users/show', [
            'user' => $user->load('roles:id,name,label', 'reportsTo:id,name,email,avatar_path')
                ->loadCount('directReports'),
            'logins' => LoginHistory::query()->where('user_id', $user->id)->latest('logged_in_at')->limit(10)
                ->get(['id', 'ip', 'browser', 'os', 'device', 'logged_in_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => $this->passwordRules(),
        ]);

        $user = User::create([...$request->only('name', 'email', 'password'), 'status' => 'active']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles($data['roles']);
        $this->forgetPermissions();

        return $this->done(__('User created successfully.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureCompanyUser($user);
        $data = $request->validate([
            ...$this->rules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
        ]);

        if (! in_array('company', $data['roles'], true) && $this->isLastCompanyUser($user)) {
            throw ValidationException::withMessages(['roles' => __('At least one active user must keep the Company role.')]);
        }

        $user->update($request->only('name', 'email'));
        $user->syncRoles($data['roles']);
        $this->forgetPermissions();

        return $this->done(__('User updated successfully.'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureCompanyUser($user);
        $request->validate(['password' => $this->passwordRules()]);

        $user->update(['password' => $request->input('password')]);

        return $this->done(__('Password reset successfully.'));
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->ensureCompanyUser($user);

        if ($user->isActive()) {
            if ($user->is($request->user())) {
                return $this->toast('error', __('You cannot deactivate your own account.'));
            }

            if ($this->isLastCompanyUser($user)) {
                return $this->toast('error', __('At least one active user must keep the Company role.'));
            }
        }

        $user->update(['status' => $user->isActive() ? 'inactive' : 'active']);

        return $this->done(__('User status updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureCompanyUser($user);

        if ($user->is($request->user())) {
            return $this->toast('error', __('You cannot delete your own account here.'));
        }

        if ($this->isLastCompanyUser($user)) {
            return $this->toast('error', __('At least one active user must keep the Company role.'));
        }

        $user->delete();

        return $this->done(__('User deleted successfully.'));
    }

    /**
     * @return Builder<User>
     */
    private function companyUsers(): Builder
    {
        return User::query()->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', 'employee'));
    }

    private function ensureCompanyUser(User $user): void
    {
        abort_if($user->hasRole('employee'), 404);
    }

    /**
     * Whether this user is the only active holder of the company role.
     */
    private function isLastCompanyUser(User $user): bool
    {
        return $user->isActive()
            && $user->hasRole('company')
            && User::role('company')->where('status', 'active')->count() <= 1;
    }

    /**
     * @return Builder<Role>
     */
    private function assignableRoles(): Builder
    {
        return Role::query()->where('name', '!=', 'employee')->orderBy('id');
    }

    private function forgetPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($this->assignableRoles()->pluck('name'))],
        ];
    }
}
