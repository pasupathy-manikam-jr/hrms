<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_signed_in_role_can_read_the_user_manual()
    {
        $this->withoutVite();

        foreach (['company', 'hr', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('user-manual'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('user-manual'));
        }
    }

    public function test_guests_are_sent_to_sign_in()
    {
        $this->get(route('user-manual'))->assertRedirect(route('login'));
    }
}
