<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function ofType(array $events, string $type): Collection
    {
        return collect($events)->where('type', $type)->values();
    }

    public function test_loads_last_to_next_year_of_holidays_leave_meetings_and_birthdays_at_once()
    {
        $this->travelTo('2026-03-15');
        $branch = Branch::factory()->create();
        Holiday::factory()->create(['name' => 'Founders Day', 'start_date' => '2024-03-10', 'end_date' => '2024-03-11', 'is_recurring' => true])->branches()->attach($branch);
        Holiday::factory()->create(['name' => 'One Off', 'start_date' => '2024-03-12', 'end_date' => '2024-03-12'])->branches()->attach($branch);
        LeaveApplication::factory()->create(['start_date' => '2026-03-02', 'end_date' => '2026-03-04', 'status' => 'approved']);
        LeaveApplication::factory()->create(['start_date' => '2026-03-02', 'end_date' => '2026-03-04', 'status' => 'pending']);
        Meeting::factory()->create(['title' => 'Planning', 'meeting_date' => '2026-03-05']);
        Meeting::factory()->create(['title' => 'Too far', 'meeting_date' => '2028-05-05']);
        Employee::factory()->create(['date_of_birth' => '1992-02-29']);

        $events = $this->actingAs($this->userWithRole())->get(route('calendar.index'))
            ->assertInertia(fn ($page) => $page->component('calendar/index')->missing('month'))
            ->viewData('page')['props']['events'];

        // Recurring holiday repeats in 2025–2027; the 2024 one-off is outside the window.
        $this->assertSame(['2025-03-10', '2026-03-10', '2027-03-10'], $this->ofType($events, 'holiday')->pluck('start')->all());
        $this->assertCount(1, $this->ofType($events, 'leave'));
        $this->assertSame(['Planning'], $this->ofType($events, 'meeting')->pluck('title')->all());
        // Leap-day birthdays fall on 28 Feb in other years.
        $this->assertContains('2026-02-28', $this->ofType($events, 'birthday')->pluck('start')->all());
    }

    public function test_employees_see_their_branch_holidays_and_own_leave_and_birthday_only()
    {
        $this->travelTo('2026-03-15');
        [$mine, $other] = Branch::factory()->count(2)->create();
        $user = $this->userWithRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'branch_id' => $mine->id, 'date_of_birth' => '1990-07-04']);

        Holiday::factory()->create(['name' => 'Ours', 'start_date' => '2026-03-10', 'end_date' => '2026-03-10'])->branches()->attach($mine);
        Holiday::factory()->create(['name' => 'Theirs', 'start_date' => '2026-03-11', 'end_date' => '2026-03-11'])->branches()->attach($other);
        LeaveApplication::factory()->create(['employee_id' => $employee->id, 'start_date' => '2026-03-02', 'end_date' => '2026-03-02', 'status' => 'approved']);
        LeaveApplication::factory()->create(['start_date' => '2026-03-02', 'end_date' => '2026-03-02', 'status' => 'approved']);

        $events = $this->actingAs($user)->get(route('calendar.index'))->viewData('page')['props']['events'];

        $this->assertSame(['Ours'], $this->ofType($events, 'holiday')->pluck('title')->all());
        $this->assertSame(['leave_'.LeaveApplication::where('employee_id', $employee->id)->value('id')], $this->ofType($events, 'leave')->pluck('id')->all());
        $this->assertSame(['2025-07-04', '2026-07-04', '2027-07-04'], $this->ofType($events, 'birthday')->pluck('start')->all());
    }

    public function test_users_without_view_calendar_are_denied()
    {
        $user = $this->userWithRole('employee');
        $user->syncRoles([]);

        $this->actingAs($user)->get(route('calendar.index'))->assertForbidden();
    }
}
