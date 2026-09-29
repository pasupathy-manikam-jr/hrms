<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_branch_and_status()
    {
        $branch = Branch::factory()->create();
        Department::factory()->count(3)->create();
        Department::factory()->create(['name' => 'Zeta Ops', 'branch_id' => $branch->id]);
        Department::factory()->create(['branch_id' => $branch->id, 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.departments.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/departments/index')
                ->has('departments.data', 1)
                ->where('departments.data.0.branch.name', $branch->name)
                ->where('statusCounts', ['all' => 5, 'active' => 4, 'inactive' => 1])
                ->has('branches', 4));

        $this->get(route('hr.departments.index', ['branch_id' => $branch->id]))
            ->assertInertia(fn ($page) => $page->has('departments.data', 2)->where('filters.branch_id', (string) $branch->id));

        $this->get(route('hr.departments.index', ['branch_id' => $branch->id, 'status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('departments.data', 1));
    }

    public function test_departments_can_be_created_updated_toggled_and_deleted()
    {
        $branch = Branch::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.departments.store'), ['name' => '', 'branch_id' => 999, 'status' => 'bogus'])
            ->assertSessionHasErrors(['name', 'branch_id', 'status']);
        $this->post(route('hr.departments.store'), ['name' => 'Legal', 'branch_id' => $branch->id, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $department = Department::where('name', 'Legal')->firstOrFail();
        $this->assertSame($branch->id, $department->branch_id);

        $this->put(route('hr.departments.update', $department), ['name' => 'Legal & Compliance', 'branch_id' => $branch->id, 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Legal & Compliance', $department->fresh()->name);

        $this->put(route('hr.departments.toggle-status', $department));
        $this->assertSame('inactive', $department->fresh()->status);

        $this->delete(route('hr.departments.destroy', $department));
        $this->assertModelMissing($department);
    }

    public function test_employees_cannot_manage_departments()
    {
        $department = Department::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.departments.index'))->assertForbidden();
        $this->post(route('hr.departments.store'), ['name' => 'X', 'branch_id' => $department->branch_id, 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.departments.toggle-status', $department))->assertForbidden();
        $this->delete(route('hr.departments.destroy', $department))->assertForbidden();
        $this->assertModelExists($department);
    }
}
