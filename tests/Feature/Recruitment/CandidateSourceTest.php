<?php

namespace Tests\Feature\Recruitment;

use App\Models\CandidateSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_status()
    {
        CandidateSource::factory()->count(3)->create();
        CandidateSource::factory()->create(['name' => 'Zeta Roles', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.candidate-sources.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/candidate-sources/index')
                ->has('candidateSources.data', 1)
                ->where('candidateSources.data.0.name', 'Zeta Roles'));

        $this->get(route('hr.recruitment.candidate-sources.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('candidateSources.data', 1)->where('filters.status', 'inactive'));
    }

    public function test_candidate_sources_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.recruitment.candidate-sources.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.recruitment.candidate-sources.store'), ['name' => 'Design', 'status' => 'active'])->assertSessionHasNoErrors();

        $record = CandidateSource::where('name', 'Design')->firstOrFail();
        $this->assertSame($user->id, $record->created_by);

        $this->put(route('hr.recruitment.candidate-sources.update', $record), ['name' => 'Product Design', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('Product Design', $record->fresh()->name);

        $before = $record->fresh()->status;
        $this->put(route('hr.recruitment.candidate-sources.toggle-status', $record))->assertSessionHasNoErrors();
        $this->assertNotSame($before, $record->fresh()->status);

        $this->delete(route('hr.recruitment.candidate-sources.destroy', $record));
        $this->assertModelMissing($record);
    }

    public function test_employees_cannot_manage_candidate_sources()
    {
        $record = CandidateSource::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.candidate-sources.index'))->assertForbidden();
        $this->post(route('hr.recruitment.candidate-sources.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.recruitment.candidate-sources.toggle-status', $record))->assertForbidden();
        $this->delete(route('hr.recruitment.candidate-sources.destroy', $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_manage_own_users_only_see_their_own_records()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-candidate-sources', 'manage-own-candidate-sources', 'edit-candidate-sources']);
        $mine = CandidateSource::factory()->create(['created_by' => $user->id]);
        $other = CandidateSource::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.recruitment.candidate-sources.index'))
            ->assertInertia(fn ($page) => $page->has('candidateSources.data', 1)->where('candidateSources.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.candidate-sources.update', $other), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
