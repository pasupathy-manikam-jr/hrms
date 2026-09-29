<?php

namespace Tests\Feature\Hr;

use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_filtered_and_counted()
    {
        Shift::factory()->count(3)->create();
        Shift::factory()->create(['name' => 'Graveyard', 'is_night_shift' => true, 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.shifts.index', ['search' => 'Grave']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/shifts/index')
                ->has('shifts.data', 1)
                ->where('shifts.data.0.name', 'Graveyard')
                ->where('typeCounts', ['night' => Shift::query()->where('is_night_shift', true)->count(), 'day' => Shift::query()->where('is_night_shift', false)->count()])
                ->where('statusCounts', ['all' => 4, 'active' => 3, 'inactive' => 1]));

        $this->get(route('hr.shifts.index', ['shift_type' => 'night']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 1)->where('filters.shift_type', 'night'));

        $this->get(route('hr.shifts.index', ['status' => 'active', 'sort_field' => 'password']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('shifts.data', 3));
    }

    public function test_shifts_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.shifts.store'), ['name' => '', 'start_time' => '25:00', 'break_duration' => -1, 'status' => 'bogus'])
            ->assertSessionHasErrors(['name', 'start_time', 'end_time', 'break_duration', 'grace_period', 'status']);

        $this->post(route('hr.shifts.store'), [
            'name' => 'Late Shift',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_duration' => 30,
            'break_start_time' => '02:00',
            'break_end_time' => '02:30',
            'grace_period' => 10,
            'is_night_shift' => true,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $shift = Shift::where('name', 'Late Shift')->firstOrFail();
        $this->assertTrue($shift->is_night_shift);

        $this->put(route('hr.shifts.update', $shift), [
            'name' => 'Late Shift B',
            'start_time' => '21:00',
            'end_time' => '05:00',
            'break_duration' => 30,
            'grace_period' => 5,
            'is_night_shift' => true,
            'status' => 'inactive',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Late Shift B', $shift->fresh()->name);
        $this->assertSame('inactive', $shift->fresh()->status);

        $this->put(route('hr.shifts.toggle-status', $shift))->assertSessionHasNoErrors();
        $this->assertSame('active', $shift->fresh()->status);

        $this->delete(route('hr.shifts.destroy', $shift));
        $this->assertModelMissing($shift);
    }

    public function test_employees_can_view_but_not_change_shifts()
    {
        $shift = Shift::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.shifts.index'))->assertOk();
        $this->post(route('hr.shifts.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.shifts.update', $shift), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.shifts.toggle-status', $shift))->assertForbidden();
        $this->delete(route('hr.shifts.destroy', $shift))->assertForbidden();
        $this->assertModelExists($shift);
    }
}
