<?php

namespace Tests\Feature\Attendance;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_can_be_assigned_a_shift()
    {
        $shift = Shift::factory()->create(['name' => 'Morning Shift']);
        $designation = Designation::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->withoutVite()->get(route('hr.employees.create'))
            ->assertInertia(fn ($page) => $page->where('shifts.0.name', 'Morning Shift'));

        $payload = [
            'name' => 'Nora Quinn',
            'email' => 'nora@example.com',
            'password' => 'Zx123456',
            'branch_id' => $designation->department->branch_id,
            'department_id' => $designation->department_id,
            'designation_id' => $designation->id,
            'date_of_joining' => '2026-01-15',
            'employment_type' => 'Full-time',
            'employee_status' => 'active',
        ];

        $this->post(route('hr.employees.store'), [...$payload, 'shift_id' => 999])->assertSessionHasErrors('shift_id');
        $this->post(route('hr.employees.store'), [...$payload, 'shift_id' => $shift->id])->assertSessionHasNoErrors();

        $employee = Employee::sole();
        $this->assertTrue($employee->shift->is($shift));

        // Optional: the shift can be cleared.
        $this->put(route('hr.employees.update', $employee), [...$payload, 'shift_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($employee->fresh()->shift_id);
    }
}
