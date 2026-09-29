<?php

namespace Tests\Feature\Performance;

use App\Models\GoalType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_status()
    {
        GoalType::factory()->count(3)->create();
        GoalType::factory()->create(['name' => 'Zeta Goals', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.performance.goal-types.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/performance/goal-types/index')
                ->has('goalTypes.data', 1)
                ->where('goalTypes.data.0.name', 'Zeta Goals')
                ->missing('statusCounts'));

        $this->get(route('hr.performance.goal-types.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('goalTypes.data', 1)->where('filters.status', 'inactive'));
    }

    public function test_goal_types_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.performance.goal-types.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.performance.goal-types.store'), ['name' => 'Sales Goals', 'status' => 'active'])->assertSessionHasNoErrors();

        $goalType = GoalType::where('name', 'Sales Goals')->firstOrFail();
        $this->assertSame($user->id, $goalType->created_by);

        $this->put(route('hr.performance.goal-types.update', $goalType), ['name' => 'Sales Goals Q2', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('Sales Goals Q2', $goalType->fresh()->name);

        $this->delete(route('hr.performance.goal-types.destroy', $goalType));
        $this->assertModelMissing($goalType);
    }

    public function test_employees_cannot_manage_goal_types()
    {
        $goalType = GoalType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.performance.goal-types.index'))->assertForbidden();
        $this->post(route('hr.performance.goal-types.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->delete(route('hr.performance.goal-types.destroy', $goalType))->assertForbidden();
        $this->assertModelExists($goalType);
    }

    public function test_manage_own_users_only_see_their_own_goal_types()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-goal-types', 'manage-own-goal-types', 'edit-goal-types']);
        $mine = GoalType::factory()->create(['created_by' => $user->id]);
        $other = GoalType::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.performance.goal-types.index'))
            ->assertInertia(fn ($page) => $page->has('goalTypes.data', 1)->where('goalTypes.data.0.id', $mine->id));

        $this->put(route('hr.performance.goal-types.update', $other), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
