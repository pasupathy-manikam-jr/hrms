<?php

namespace Tests\Feature\Hr;

use App\Models\Department;
use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_department()
    {
        $department = Department::factory()->create();
        Designation::factory()->count(3)->create();
        Designation::factory()->create(['name' => 'Zeta Analyst', 'department_id' => $department->id]);
        Designation::factory()->create(['department_id' => $department->id]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.designations.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/designations/index')
                ->has('designations.data', 1)
                ->where('designations.data.0.department.name', $department->name)
                ->has('departments', 4));

        $this->get(route('hr.designations.index', ['department' => $department->id]))
            ->assertInertia(fn ($page) => $page->has('designations.data', 2)->where('filters.department', (string) $department->id));
    }

    public function test_designations_can_be_created_updated_toggled_and_deleted()
    {
        $department = Department::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.designations.store'), ['name' => '', 'department_id' => 999, 'status' => 'bogus'])
            ->assertSessionHasErrors(['name', 'department_id', 'status']);
        $this->post(route('hr.designations.store'), ['name' => 'Paralegal', 'department_id' => $department->id, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $designation = Designation::where('name', 'Paralegal')->firstOrFail();

        $this->put(route('hr.designations.update', $designation), ['name' => 'Senior Paralegal', 'department_id' => $department->id, 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Senior Paralegal', $designation->fresh()->name);

        $this->put(route('hr.designations.toggle-status', $designation));
        $this->assertSame('inactive', $designation->fresh()->status);

        $this->delete(route('hr.designations.destroy', $designation));
        $this->assertModelMissing($designation);
    }

    public function test_employees_cannot_manage_designations()
    {
        $designation = Designation::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.designations.index'))->assertForbidden();
        $this->post(route('hr.designations.store'), ['name' => 'X', 'department_id' => $designation->department_id, 'status' => 'active'])->assertForbidden();
        $this->delete(route('hr.designations.destroy', $designation))->assertForbidden();
        $this->assertModelExists($designation);
    }
}
