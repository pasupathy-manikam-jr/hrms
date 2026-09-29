<?php

namespace Tests\Feature\Hr;

use App\Models\AwardType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        AwardType::factory()->count(3)->create();
        AwardType::factory()->create(['name' => 'zzz Hero Award', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.award-types.index', ['search' => 'Hero']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/award-types/index')
                ->has('awardTypes.data', 1)
                ->where('awardTypes.data.0.name', 'zzz Hero Award'));

        $this->get(route('hr.award-types.index', ['status' => 'inactive']))->assertInertia(fn ($page) => $page->has('awardTypes.data', 1));
        $this->get(route('hr.award-types.index', ['sort_field' => 'name', 'sort_direction' => 'desc']))
            ->assertInertia(fn ($page) => $page->has('awardTypes.data', 4)->where('awardTypes.data.0.name', 'zzz Hero Award'));
    }

    public function test_award_types_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.award-types.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.award-types.store'), ['name' => 'Team Player', 'status' => 'active'])->assertSessionHasNoErrors();

        $awardType = AwardType::where('name', 'Team Player')->firstOrFail();
        $this->assertSame($user->id, $awardType->created_by);

        $this->put(route('hr.award-types.update', $awardType), ['name' => 'Team Spirit', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $awardType->fresh()->status);

        $this->put(route('hr.award-types.toggle-status', $awardType))->assertSessionHasNoErrors();
        $this->assertSame('active', $awardType->fresh()->status);

        $this->delete(route('hr.award-types.destroy', $awardType));
        $this->assertModelMissing($awardType);
    }

    public function test_employees_cannot_manage_award_types()
    {
        $awardType = AwardType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.award-types.index'))->assertForbidden();
        $this->post(route('hr.award-types.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.award-types.toggle-status', $awardType))->assertForbidden();
        $this->delete(route('hr.award-types.destroy', $awardType))->assertForbidden();
        $this->assertModelExists($awardType);
    }
}
