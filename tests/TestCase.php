<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a user holding one of the seeded roles (company, hr, employee).
     */
    protected function userWithRole(string $role = 'company'): User
    {
        $this->seed(RolesSeeder::class);

        return User::factory()->create()->assignRole($role);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
