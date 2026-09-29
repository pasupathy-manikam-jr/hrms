<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceIndicator;
use App\Models\PerformanceIndicatorCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_category()
    {
        $category = PerformanceIndicatorCategory::factory()->create();
        PerformanceIndicator::factory()->count(3)->create();
        PerformanceIndicator::factory()->create(['name' => 'Zeta Accuracy', 'category_id' => $category->id]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.performance.indicators.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/performance/indicators/index')
                ->has('indicators.data', 1)
                ->where('indicators.data.0.category.name', $category->name)
                ->has('categories', 4));

        $this->get(route('hr.performance.indicators.index', ['category_id' => $category->id]))
            ->assertInertia(fn ($page) => $page->has('indicators.data', 1)->where('statusCounts.all', 1));
    }

    public function test_indicators_can_be_created_updated_toggled_and_deleted()
    {
        $category = PerformanceIndicatorCategory::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.performance.indicators.store'), ['name' => '', 'category_id' => 999, 'measurement_unit' => 'Parsecs', 'status' => 'active'])
            ->assertSessionHasErrors(['name', 'category_id', 'measurement_unit']);
        $this->post(route('hr.performance.indicators.store'), [
            'category_id' => $category->id, 'name' => 'On-time Delivery', 'measurement_unit' => 'Percentage', 'target_value' => '95%', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $indicator = PerformanceIndicator::where('name', 'On-time Delivery')->firstOrFail();
        $this->assertSame('95%', $indicator->target_value);

        $this->put(route('hr.performance.indicators.update', $indicator), [
            'category_id' => $category->id, 'name' => 'On-time Delivery', 'measurement_unit' => 'Percentage', 'target_value' => '98%', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('98%', $indicator->fresh()->target_value);

        $this->put(route('hr.performance.indicators.toggle-status', $indicator));
        $this->assertSame('inactive', $indicator->fresh()->status);

        $this->delete(route('hr.performance.indicators.destroy', $indicator));
        $this->assertModelMissing($indicator);
    }

    public function test_employees_can_only_view_indicators()
    {
        $indicator = PerformanceIndicator::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        // manage-own only: the employee sees none of the company's indicators.
        $this->get(route('hr.performance.indicators.index'))->assertInertia(fn ($page) => $page->has('indicators.data', 0));
        $this->post(route('hr.performance.indicators.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.performance.indicators.toggle-status', $indicator))->assertForbidden();
        $this->delete(route('hr.performance.indicators.destroy', $indicator))->assertForbidden();
        $this->assertModelExists($indicator);
    }
}
