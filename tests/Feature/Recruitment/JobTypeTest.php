<?php

namespace Tests\Feature\Recruitment;

use App\Models\JobType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_status()
    {
        JobType::factory()->count(3)->create();
        JobType::factory()->create(['name' => 'Zeta Roles', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.job-types.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/job-types/index')
                ->has('jobTypes.data', 1)
                ->where('jobTypes.data.0.name', 'Zeta Roles'));

        $this->get(route('hr.recruitment.job-types.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('jobTypes.data', 1)->where('filters.status', 'inactive'));
    }

    public function test_job_types_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.recruitment.job-types.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.recruitment.job-types.store'), ['name' => 'Design', 'status' => 'active'])->assertSessionHasNoErrors();

        $record = JobType::where('name', 'Design')->firstOrFail();
        $this->assertSame($user->id, $record->created_by);

        $this->put(route('hr.recruitment.job-types.update', $record), ['name' => 'Product Design', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('Product Design', $record->fresh()->name);

        $before = $record->fresh()->status;
        $this->put(route('hr.recruitment.job-types.toggle-status', $record))->assertSessionHasNoErrors();
        $this->assertNotSame($before, $record->fresh()->status);

        $this->delete(route('hr.recruitment.job-types.destroy', $record));
        $this->assertModelMissing($record);
    }

    public function test_employees_cannot_manage_job_types()
    {
        $record = JobType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.job-types.index'))->assertForbidden();
        $this->post(route('hr.recruitment.job-types.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.recruitment.job-types.toggle-status', $record))->assertForbidden();
        $this->delete(route('hr.recruitment.job-types.destroy', $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_manage_own_users_only_see_their_own_records()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-job-types', 'manage-own-job-types', 'edit-job-types']);
        $mine = JobType::factory()->create(['created_by' => $user->id]);
        $other = JobType::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.recruitment.job-types.index'))
            ->assertInertia(fn ($page) => $page->has('jobTypes.data', 1)->where('jobTypes.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.job-types.update', $other), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
