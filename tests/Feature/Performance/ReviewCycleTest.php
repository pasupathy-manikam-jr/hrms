<?php

namespace Tests\Feature\Performance;

use App\Models\ReviewCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_frequency()
    {
        ReviewCycle::factory()->count(3)->create();
        ReviewCycle::factory()->create(['name' => 'Zeta Annual', 'frequency' => 'Annual']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.performance.review-cycles.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/performance/review-cycles/index')
                ->has('reviewCycles.data', 1)
                ->where('reviewCycles.data.0.name', 'Zeta Annual'));

        $this->get(route('hr.performance.review-cycles.index', ['frequency' => 'Annual']))
            ->assertInertia(fn ($page) => $page->has('reviewCycles.data', 1)->where('filters.frequency', 'Annual'));
    }

    public function test_review_cycles_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.performance.review-cycles.store'), ['name' => '', 'frequency' => 'Hourly', 'start_date' => '2026-06-01', 'end_date' => '2026-01-01', 'status' => 'active'])
            ->assertSessionHasErrors(['name', 'frequency', 'end_date']);
        $this->post(route('hr.performance.review-cycles.store'), ['name' => 'H1 2026', 'frequency' => 'Semi-Annual', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $cycle = ReviewCycle::where('name', 'H1 2026')->firstOrFail();
        $this->assertSame('2026-06-30', $cycle->end_date?->toDateString());

        $this->put(route('hr.performance.review-cycles.update', $cycle), ['name' => 'H1 2026', 'frequency' => 'Quarterly', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('Quarterly', $cycle->fresh()->frequency);

        $this->delete(route('hr.performance.review-cycles.destroy', $cycle));
        $this->assertModelMissing($cycle);
    }

    public function test_employees_cannot_change_review_cycles()
    {
        $cycle = ReviewCycle::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.performance.review-cycles.index'))->assertInertia(fn ($page) => $page->has('reviewCycles.data', 0));
        $this->post(route('hr.performance.review-cycles.store'), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.performance.review-cycles.destroy', $cycle))->assertForbidden();
        $this->assertModelExists($cycle);
    }
}
