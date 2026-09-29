<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_shows_the_employee()
    {
        $this->withoutVite();
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole())
            ->get(route('hr.employees.show', $employee))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employees/show')
                ->where('employee.id', $employee->id)
                ->where('employee.user.email', $employee->user->email)
                ->has('certifications')
                ->has('contracts'));
    }

    public function test_employees_can_only_open_their_own_profile()
    {
        $this->withoutVite();
        $user = $this->userWithRole('employee');
        $own = Employee::factory()->create(['user_id' => $user->id]);
        $other = Employee::factory()->create();

        $this->actingAs($user)->get(route('hr.employees.show', $own))->assertOk();
        $this->get(route('hr.employees.show', $other))->assertNotFound();
    }
}
