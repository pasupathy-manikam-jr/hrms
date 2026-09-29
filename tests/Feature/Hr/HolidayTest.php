<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        $branch = Branch::factory()->create();
        Holiday::factory()->count(3)->create(['category' => 'national']);
        Holiday::factory()->create(['name' => 'Founders Day', 'category' => 'company-specific', 'start_date' => '2025-03-01', 'end_date' => '2025-03-01'])
            ->branches()->attach($branch);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.holidays.index', ['search' => 'Founders']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/holidays/index')
                ->has('holidays.data', 1)
                ->where('holidays.data.0.branches.0.id', $branch->id)
                ->where('categories', Holiday::CATEGORIES));

        $this->get(route('hr.holidays.index', ['category' => 'company-specific']))->assertInertia(fn ($page) => $page->has('holidays.data', 1));
        $this->get(route('hr.holidays.index', ['branch_id' => $branch->id]))->assertInertia(fn ($page) => $page->has('holidays.data', 1));
        $this->get(route('hr.holidays.index', ['year' => 2025]))->assertInertia(fn ($page) => $page->has('holidays.data', 1)->where('years.0', 2025));
        $this->get(route('hr.holidays.index', ['date_from' => '2025-02-01', 'date_to' => '2025-03-31']))->assertInertia(fn ($page) => $page->has('holidays.data', 1));
    }

    public function test_holidays_can_be_created_updated_and_deleted()
    {
        [$a, $b] = Branch::factory()->count(2)->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.holidays.store'), ['name' => '', 'category' => 'bogus', 'start_date' => '2026-05-02', 'end_date' => '2026-05-01', 'branch_ids' => []])
            ->assertSessionHasErrors(['name', 'category', 'end_date', 'branch_ids']);

        $this->post(route('hr.holidays.store'), [
            'name' => 'Spring Break', 'category' => 'regional', 'start_date' => '2026-05-01', 'end_date' => '2026-05-02',
            'is_paid' => false, 'is_half_day' => false, 'is_recurring' => true, 'branch_ids' => [$a->id, $b->id],
        ])->assertSessionHasNoErrors();

        $holiday = Holiday::where('name', 'Spring Break')->firstOrFail();
        $this->assertTrue($holiday->is_recurring);
        $this->assertCount(2, $holiday->branches);

        $this->put(route('hr.holidays.update', $holiday), [
            'name' => 'Spring Holiday', 'category' => 'regional', 'start_date' => '2026-05-01', 'end_date' => '2026-05-01', 'branch_ids' => [$b->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Spring Holiday', $holiday->fresh()->name);
        $this->assertSame([$b->id], $holiday->branches()->pluck('branches.id')->all());

        $this->delete(route('hr.holidays.destroy', $holiday));
        $this->assertModelMissing($holiday);
    }

    public function test_employees_can_view_but_not_change_holidays()
    {
        $holiday = Holiday::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.holidays.index'))->assertOk();
        $this->post(route('hr.holidays.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.holidays.update', $holiday), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.holidays.destroy', $holiday))->assertForbidden();
        $this->assertModelExists($holiday);
    }
}
