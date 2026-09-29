<?php

namespace Tests\Feature\Recruitment;

use App\Models\JobCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_status()
    {
        JobCategory::factory()->count(3)->create();
        JobCategory::factory()->create(['name' => 'Zeta Roles', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.job-categories.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/job-categories/index')
                ->has('jobCategories.data', 1)
                ->where('jobCategories.data.0.name', 'Zeta Roles'));

        $this->get(route('hr.recruitment.job-categories.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('jobCategories.data', 1)->where('filters.status', 'inactive'));
    }

    public function test_job_categories_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.recruitment.job-categories.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.recruitment.job-categories.store'), ['name' => 'Design', 'status' => 'active'])->assertSessionHasNoErrors();

        $category = JobCategory::where('name', 'Design')->firstOrFail();
        $this->assertSame($user->id, $category->created_by);

        $this->put(route('hr.recruitment.job-categories.update', $category), ['name' => 'Product Design', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('Product Design', $category->fresh()->name);

        $before = $category->fresh()->status;
        $this->put(route('hr.recruitment.job-categories.toggle-status', $category))->assertSessionHasNoErrors();
        $this->assertNotSame($before, $category->fresh()->status);

        $this->delete(route('hr.recruitment.job-categories.destroy', $category));
        $this->assertModelMissing($category);
    }

    public function test_employees_cannot_manage_job_categories()
    {
        $category = JobCategory::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.job-categories.index'))->assertForbidden();
        $this->post(route('hr.recruitment.job-categories.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.recruitment.job-categories.toggle-status', $category))->assertForbidden();
        $this->delete(route('hr.recruitment.job-categories.destroy', $category))->assertForbidden();
        $this->assertModelExists($category);
    }

    public function test_manage_own_users_only_see_their_own_categories()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-job-categories', 'manage-own-job-categories', 'edit-job-categories']);
        $mine = JobCategory::factory()->create(['created_by' => $user->id]);
        $other = JobCategory::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.recruitment.job-categories.index'))
            ->assertInertia(fn ($page) => $page->has('jobCategories.data', 1)->where('jobCategories.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.job-categories.update', $other), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
