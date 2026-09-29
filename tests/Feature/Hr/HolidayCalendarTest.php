<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_repeats_recurring_holidays_and_applies_filters()
    {
        $this->travelTo('2026-09-27');
        [$north, $south] = Branch::factory()->count(2)->create();

        Holiday::factory()->create(['name' => 'New Year', 'category' => 'national', 'start_date' => '2020-01-01', 'end_date' => '2020-01-01', 'is_recurring' => true])
            ->branches()->attach([$north->id, $south->id]);
        Holiday::factory()->create(['name' => 'Harvest', 'category' => 'regional', 'start_date' => '2026-10-05', 'end_date' => '2026-10-06'])
            ->branches()->attach($south);
        Holiday::factory()->create(['name' => 'Long Gone', 'start_date' => '2019-05-01', 'end_date' => '2019-05-01']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.holidays.calendar'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/holidays/calendar')
                // New Year in 2025, 2026 and 2027 (last year to next year) plus Harvest.
                ->has('calendarEvents', 4)
                ->where('calendarEvents', fn ($events) => collect($events)->pluck('start')->sort()->values()->all() === ['2025-01-01', '2026-01-01', '2026-10-05', '2027-01-01'])
                ->where('calendarEvents', fn ($events) => collect($events)->firstWhere('title', 'Harvest')['end'] === '2026-10-06')
                ->where('categories', Holiday::CATEGORIES)
                ->has('branches', 2));

        $this->get(route('hr.holidays.calendar', ['branch_id' => $north->id]))
            ->assertInertia(fn ($page) => $page->has('calendarEvents', 3)->where('filters.branch_id', (string) $north->id));
        $this->get(route('hr.holidays.calendar', ['category' => 'regional']))
            ->assertInertia(fn ($page) => $page->has('calendarEvents', 1)->where('calendarEvents.0.branches.0', $south->name));
    }

    public function test_own_scope_and_permission_denial()
    {
        $this->userWithRole(); // seeds the permissions
        $viewer = User::factory()->create()->givePermissionTo(['manage-holidays', 'manage-own-holidays']);
        Holiday::factory()->create(['name' => 'Mine', 'start_date' => today(), 'end_date' => today(), 'created_by' => $viewer->id]);
        Holiday::factory()->create(['start_date' => today(), 'end_date' => today()]);

        $this->actingAs($viewer)
            ->get(route('hr.holidays.calendar'))
            ->assertInertia(fn ($page) => $page->has('calendarEvents', 1)->where('calendarEvents.0.title', 'Mine'));

        $this->actingAs(User::factory()->create())
            ->get(route('hr.holidays.calendar'))
            ->assertForbidden();
    }
}
