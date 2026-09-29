<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceIndicatorCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_status()
    {
        PerformanceIndicatorCategory::factory()->count(3)->create();
        PerformanceIndicatorCategory::factory()->create(['name' => 'Zeta Skills', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.performance.indicator-categories.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/performance/indicator-categories/index')
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'Zeta Skills')
                ->where('statusCounts', ['all' => 4, 'active' => 3, 'inactive' => 1]));

        $this->get(route('hr.performance.indicator-categories.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('categories.data', 1)->where('filters.status', 'inactive'));
    }

    public function test_categories_can_be_created_updated_toggled_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.performance.indicator-categories.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.performance.indicator-categories.store'), ['name' => 'Delivery', 'status' => 'active'])->assertSessionHasNoErrors();

        $category = PerformanceIndicatorCategory::where('name', 'Delivery')->firstOrFail();
        $this->assertSame($user->id, $category->created_by);

        $this->put(route('hr.performance.indicator-categories.update', $category), ['name' => 'Delivery Quality', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('Delivery Quality', $category->fresh()->name);

        $this->put(route('hr.performance.indicator-categories.toggle-status', $category));
        $this->assertSame('inactive', $category->fresh()->status);

        $this->delete(route('hr.performance.indicator-categories.destroy', $category));
        $this->assertModelMissing($category);
    }

    public function test_employees_cannot_manage_categories()
    {
        $category = PerformanceIndicatorCategory::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.performance.indicator-categories.index'))->assertForbidden();
        $this->post(route('hr.performance.indicator-categories.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->delete(route('hr.performance.indicator-categories.destroy', $category))->assertForbidden();
        $this->assertModelExists($category);
    }

    public function test_manage_own_users_only_see_their_own_categories()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-performance-indicator-categories', 'manage-own-performance-indicator-categories', 'edit-performance-indicator-categories']);
        $mine = PerformanceIndicatorCategory::factory()->create(['created_by' => $user->id]);
        $other = PerformanceIndicatorCategory::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.performance.indicator-categories.index'))
            ->assertInertia(fn ($page) => $page->has('categories.data', 1)->where('categories.data.0.id', $mine->id));

        $this->put(route('hr.performance.indicator-categories.update', $other), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
