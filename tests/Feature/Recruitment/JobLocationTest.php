<?php

namespace Tests\Feature\Recruitment;

use App\Models\JobLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_has_status_counts_and_remote_filter()
    {
        JobLocation::factory()->count(2)->create();
        JobLocation::factory()->create(['name' => 'Remote - Global', 'is_remote' => true, 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.job-locations.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/job-locations/index')
                ->has('jobLocations.data', 3)
                ->where('statusCounts', ['all' => 3, 'active' => 2, 'inactive' => 1]));

        $this->get(route('hr.recruitment.job-locations.index', ['is_remote' => '1']))
            ->assertInertia(fn ($page) => $page->has('jobLocations.data', 1)->where('jobLocations.data.0.name', 'Remote - Global'));

        $this->get(route('hr.recruitment.job-locations.index', ['is_remote' => '0', 'status' => 'active']))
            ->assertInertia(fn ($page) => $page->has('jobLocations.data', 2));
    }

    public function test_job_locations_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.recruitment.job-locations.store'), ['name' => '', 'status' => 'bogus', 'is_remote' => 'x'])->assertSessionHasErrors(['name', 'status', 'is_remote']);
        $this->post(route('hr.recruitment.job-locations.store'), ['name' => 'Tech Hub', 'city' => 'Pune', 'is_remote' => false, 'status' => 'active'])->assertSessionHasNoErrors();

        $location = JobLocation::where('name', 'Tech Hub')->firstOrFail();

        $this->put(route('hr.recruitment.job-locations.update', $location), ['name' => 'Remote Hub', 'is_remote' => true, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertTrue($location->fresh()->is_remote);

        $before = $location->fresh()->status;
        $this->put(route('hr.recruitment.job-locations.toggle-status', $location))->assertSessionHasNoErrors();
        $this->assertNotSame($before, $location->fresh()->status);

        $this->delete(route('hr.recruitment.job-locations.destroy', $location));
        $this->assertModelMissing($location);
    }

    public function test_employees_can_view_but_not_manage_job_locations()
    {
        $location = JobLocation::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.job-locations.index'))->assertOk();
        $this->post(route('hr.recruitment.job-locations.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.recruitment.job-locations.update', $location), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.recruitment.job-locations.toggle-status', $location))->assertForbidden();
        $this->delete(route('hr.recruitment.job-locations.destroy', $location))->assertForbidden();
        $this->assertModelExists($location);
    }
}
