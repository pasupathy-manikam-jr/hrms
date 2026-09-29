<?php

namespace Tests\Feature\Training;

use App\Models\Branch;
use App\Models\Department;
use App\Models\TrainingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_department()
    {
        $department = Department::factory()->create();
        TrainingType::create(['name' => 'Leadership', 'branch_id' => $department->branch_id])->departments()->attach($department);
        TrainingType::create(['name' => 'Safety']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.training-types.index', ['search' => 'Lead']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-types/index')
                ->has('trainingTypes.data', 1)
                ->where('trainingTypes.data.0.name', 'Leadership')
                ->where('trainingTypes.data.0.departments.0.id', $department->id));

        $this->get(route('hr.training-types.index', ['department_id' => $department->id]))
            ->assertInertia(fn ($page) => $page->has('trainingTypes.data', 1));

        $this->get(route('hr.training-types.index', ['sort_field' => 'password']))->assertOk();
    }

    public function test_training_types_can_be_created_updated_and_deleted()
    {
        $department = Department::factory()->create();
        $otherBranch = Branch::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.training-types.store'), ['name' => ''])->assertSessionHasErrors('name');
        // Departments must belong to the selected branch.
        $this->post(route('hr.training-types.store'), ['name' => 'Soft Skills', 'branch_id' => $otherBranch->id, 'department_ids' => [$department->id]])
            ->assertSessionHasErrors('department_ids.0');

        $this->post(route('hr.training-types.store'), ['name' => 'Soft Skills', 'branch_id' => $department->branch_id, 'department_ids' => [$department->id]])
            ->assertSessionHasNoErrors();

        $type = TrainingType::where('name', 'Soft Skills')->firstOrFail();
        $this->assertSame([$department->id], $type->departments()->pluck('departments.id')->all());

        $this->put(route('hr.training-types.update', $type), ['name' => 'People Skills', 'branch_id' => null])->assertSessionHasNoErrors();
        $this->assertSame('People Skills', $type->fresh()->name);
        $this->assertCount(0, $type->departments()->get());

        $this->delete(route('hr.training-types.destroy', $type));
        $this->assertModelMissing($type);
    }

    public function test_employees_cannot_manage_training_types()
    {
        $type = TrainingType::create(['name' => 'Safety']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.training-types.index'))->assertForbidden();
        $this->post(route('hr.training-types.store'), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.training-types.destroy', $type))->assertForbidden();
        $this->assertModelExists($type);
    }

    public function test_departments_can_be_assigned_within_the_types_branch()
    {
        $department = Department::factory()->create();
        $sibling = Department::factory()->create(['branch_id' => $department->branch_id]);
        $elsewhere = Department::factory()->create();
        $type = TrainingType::create(['name' => 'Leadership', 'branch_id' => $department->branch_id]);
        $type->departments()->attach($department);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.training-types.assign-departments', $type), ['department_ids' => []])->assertSessionHasErrors('department_ids');
        $this->put(route('hr.training-types.assign-departments', $type), ['department_ids' => [$elsewhere->id]])->assertSessionHasErrors('department_ids.0');

        $this->put(route('hr.training-types.assign-departments', $type), ['department_ids' => [$sibling->id]])->assertSessionHasNoErrors();
        $this->assertEquals([$sibling->id], $type->departments()->pluck('departments.id')->all());

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('hr.training-types.assign-departments', $type), ['department_ids' => [$department->id]])
            ->assertForbidden();
    }
}
