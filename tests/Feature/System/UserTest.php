<?php

namespace Tests\Feature\System;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_excludes_employees_and_filters_by_search_role_and_status()
    {
        $admin = $this->userWithRole();
        User::factory()->create(['name' => 'Harriet Hr'])->assignRole('hr');
        User::factory()->create(['name' => 'Idle Manager', 'status' => 'inactive'])->assignRole('manager');
        User::factory()->create(['name' => 'Eve Employee'])->assignRole('employee');

        $this->actingAs($admin)->get(route('users.index'))
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->has('users.data', 3)
                ->where('roles', fn ($roles) => ! collect($roles)->contains('name', 'employee')));

        $this->get(route('users.index', ['search' => 'Harriet']))->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.roles.0.name', 'hr'));
        $this->get(route('users.index', ['role' => 'manager']))->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.name', 'Idle Manager'));
        $this->get(route('users.index', ['status' => 'inactive']))->assertInertia(fn ($page) => $page->has('users.data', 1)->where('filters.status', 'inactive'));
    }

    public function test_users_can_be_created_updated_reset_toggled_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('users.store'), ['name' => '', 'email' => 'bad', 'password' => 'x', 'roles' => ['employee']])
            ->assertSessionHasErrors(['name', 'email', 'password', 'roles.0']);

        $this->post(route('users.store'), [
            'name' => 'Nia', 'email' => 'nia@example.com', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'roles' => ['hr'],
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'nia@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('hr'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('active', $user->status);

        $this->put(route('users.update', $user), ['name' => 'Nia K', 'email' => 'nia@example.com', 'roles' => ['manager']])->assertSessionHasNoErrors();
        $this->assertSame('Nia K', $user->fresh()->name);
        $this->assertSame(['manager'], $user->fresh()->getRoleNames()->all());

        $this->put(route('users.reset-password', $user), ['password' => 'Another1!', 'password_confirmation' => 'Another1!'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Another1!', $user->fresh()->password));

        $this->put(route('users.toggle-status', $user));
        $this->assertSame('inactive', $user->fresh()->status);

        $this->delete(route('users.destroy', $user));
        $this->assertModelMissing($user);
    }

    public function test_employees_are_not_managed_here()
    {
        $this->actingAs($this->userWithRole());
        $employee = User::factory()->create()->assignRole('employee');

        $this->delete(route('users.destroy', $employee))->assertNotFound();
        $this->assertModelExists($employee);
    }

    public function test_users_cannot_delete_or_deactivate_themselves()
    {
        $admin = $this->userWithRole();
        User::factory()->create()->assignRole('company');
        $this->actingAs($admin);

        $this->put(route('users.toggle-status', $admin))->assertSessionHas('inertia.flash_data.toast.type', 'error');
        $this->delete(route('users.destroy', $admin))->assertSessionHas('inertia.flash_data.toast.type', 'error');
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_the_last_active_company_user_is_protected()
    {
        $last = $this->userWithRole('company');
        // A non-company user with user-management permissions, so self-protection doesn't mask the rule.
        $hrAdmin = User::factory()->create()->givePermissionTo(['manage-users', 'edit-users', 'delete-users', 'toggle-status-users']);
        User::factory()->create(['status' => 'inactive'])->assignRole('company');

        $this->actingAs($hrAdmin);

        $this->delete(route('users.destroy', $last))->assertSessionHas('inertia.flash_data.toast.message', 'At least one active user must keep the Company role.');
        $this->put(route('users.toggle-status', $last))->assertSessionHas('inertia.flash_data.toast.type', 'error');
        $this->put(route('users.update', $last), ['name' => $last->name, 'email' => $last->email, 'roles' => ['hr']])->assertSessionHasErrors('roles');

        $this->assertModelExists($last);
        $this->assertTrue($last->fresh()->isActive());
        $this->assertTrue($last->fresh()->hasRole('company'));

        // With a second active company user, the first can go.
        User::factory()->create()->assignRole('company');
        $this->delete(route('users.destroy', $last))->assertSessionHas('inertia.flash_data.toast.type', 'success');
        $this->assertModelMissing($last);
    }

    public function test_deactivated_users_cannot_log_in()
    {
        $user = $this->userWithRole('hr');
        $user->update(['status' => 'inactive']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Your account has been deactivated.']);
        $this->assertGuest();

        // Wrong passwords still get the generic error, not the status.
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_deactivating_a_user_ends_their_session_on_the_next_request()
    {
        $user = $this->userWithRole('hr');
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['status' => 'inactive']);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_hr_and_employees_cannot_manage_users()
    {
        $target = User::factory()->create();

        foreach (['hr', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(route('users.index'))->assertForbidden();
            $this->post(route('users.store'), ['name' => 'X'])->assertForbidden();
            $this->put(route('users.update', $target), ['name' => 'X'])->assertForbidden();
            $this->put(route('users.toggle-status', $target))->assertForbidden();
            $this->put(route('users.reset-password', $target), ['password' => 'x'])->assertForbidden();
            $this->delete(route('users.destroy', $target))->assertForbidden();
        }

        $this->assertModelExists($target);
    }

    public function test_user_page_shows_profile_roles_and_recent_logins()
    {
        $this->withoutVite();
        $admin = $this->userWithRole();
        $user = User::factory()->create()->assignRole('hr');
        LoginHistory::create(['user_id' => $user->id, 'ip' => '10.0.0.1', 'browser' => 'Chrome', 'logged_in_at' => now()]);

        $this->actingAs($admin)
            ->get(route('users.show', $user))
            ->assertInertia(fn ($page) => $page
                ->component('users/show')
                ->where('user.id', $user->id)
                ->where('user.roles.0.name', 'hr')
                ->missing('user.password')
                ->where('logins.0.ip', '10.0.0.1'));
    }

    public function test_employee_users_have_no_page_here()
    {
        $this->withoutVite();
        $admin = $this->userWithRole();
        $employee = User::factory()->create()->assignRole('employee');

        $this->actingAs($admin)->get(route('users.show', $employee))->assertNotFound();
    }
}
