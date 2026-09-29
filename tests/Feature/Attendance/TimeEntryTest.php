<?php

namespace Tests\Feature\Attendance;

use App\Models\Employee;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(array $attributes = []): TimeEntry
    {
        return TimeEntry::create([
            'employee_id' => Employee::factory()->create()->id,
            'date' => '2026-09-21',
            'project' => 'Testing',
            'description' => 'Wrote tests',
            'hours' => 4,
            'status' => 'pending',
            ...$attributes,
        ]);
    }

    public function test_weekly_grid_sums_each_employees_days_and_week(): void
    {
        $first = $this->entry(['hours' => 7.5]);
        $this->entry(['employee_id' => $first->employee_id, 'hours' => 1, 'status' => 'approved']);
        $this->entry(['employee_id' => $first->employee_id, 'date' => '2026-09-23', 'hours' => 2, 'status' => 'approved']);
        $this->entry(['date' => '2026-09-28']); // following week

        $this->actingAs($this->userWithRole('hr'))
            // Any day of the week opens that Monday-to-Sunday week.
            ->get(route('hr.time-entries.index', ['week_start' => '2026-09-24']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/time-entries/index')
                ->where('weekStart', '2026-09-21')
                ->has('entries', 3)
                ->where('statusCounts', ['all' => 4, 'pending' => 2, 'approved' => 2, 'rejected' => 0])
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('employee.id', $first->employee_id) === [
                    'employee' => collect($rows)->firstWhere('employee.id', $first->employee_id)['employee'],
                    'days' => [
                        // A day with any pending entry shows as pending.
                        '2026-09-21' => ['hours' => 8.5, 'entries' => 2, 'status' => 'pending'],
                        '2026-09-23' => ['hours' => 2, 'entries' => 1, 'status' => 'approved'],
                    ],
                    'total' => 10.5,
                ]));

        $this->get(route('hr.time-entries.index', ['week_start' => '2026-09-21', 'status' => 'approved']))
            ->assertInertia(fn ($page) => $page->has('entries', 2));

        $this->get(route('hr.time-entries.index', ['week_start' => 'nope']))->assertSessionHasErrors('week_start');
    }

    public function test_employee_logs_their_own_time_and_sees_only_their_own(): void
    {
        $user = $this->userWithRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $other = $this->entry();

        $this->actingAs($user)->post(route('hr.time-entries.store'), [
            'employee_id' => $other->employee_id,
            'date' => '2026-09-22',
            'project' => 'Mobile App',
            'description' => 'Login screen',
            'hours' => 3.5,
            'start_time' => '09:00',
            'end_time' => '12:30',
            'is_billable' => true,
            'status' => 'approved', // ignored: new entries are always pending
        ])->assertSessionHasNoErrors();

        $own = TimeEntry::query()->where('description', 'Login screen')->sole();
        $this->assertSame($employee->id, $own->employee_id);
        $this->assertSame('pending', $own->status);
        $this->assertTrue($own->is_billable);

        $this->get(route('hr.time-entries.index', ['week_start' => '2026-09-21']))
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.employee.id', $employee->id)
                ->has('entries', 1)
                ->where('entries.0.id', $own->id)
                ->where('employees', []));

        $this->put(route('hr.time-entries.update', $other), [])->assertNotFound();
        $this->delete(route('hr.time-entries.destroy', $own))->assertForbidden(); // no delete-time-entries
        $this->put(route('hr.time-entries.approve', $own))->assertForbidden();

        $own->update(['status' => 'approved']);
        $this->put(route('hr.time-entries.update', $own), ['date' => '2026-09-22', 'description' => 'Changed', 'hours' => 1])->assertForbidden();
        $this->assertSame('approved', $own->fresh()?->status);
    }

    public function test_hours_must_be_positive_at_most_24_and_a_day_at_most_24(): void
    {
        $existing = $this->entry(['hours' => 20]);
        $this->actingAs($this->userWithRole('company'));
        $payload = ['employee_id' => $existing->employee_id, 'date' => '2026-09-21', 'description' => 'More'];

        $this->post(route('hr.time-entries.store'), [...$payload, 'hours' => 0])->assertSessionHasErrors('hours');
        $this->post(route('hr.time-entries.store'), [...$payload, 'date' => '2026-09-22', 'hours' => 24.5])->assertSessionHasErrors('hours');
        $this->post(route('hr.time-entries.store'), [...$payload, 'hours' => 4.5])->assertSessionHasErrors('hours');
        $this->post(route('hr.time-entries.store'), [...$payload, 'hours' => 2, 'start_time' => '10:00', 'end_time' => '09:00'])->assertSessionHasErrors('end_time');
        $this->post(route('hr.time-entries.store'), [])->assertSessionHasErrors(['employee_id', 'date', 'description', 'hours']);
        $this->assertSame(1, TimeEntry::query()->count());

        $this->post(route('hr.time-entries.store'), [...$payload, 'hours' => 4])->assertSessionHasNoErrors();

        // Editing an entry doesn't count its own hours twice, and sends it back for approval.
        $existing->update(['status' => 'rejected']);
        $this->put(route('hr.time-entries.update', $existing), [...$payload, 'description' => 'Edited', 'hours' => 20])->assertSessionHasNoErrors();
        $this->assertSame(['Edited', 'pending'], [$existing->fresh()?->description, $existing->fresh()?->status]);
    }

    public function test_staff_approve_or_reject_pending_entries_and_delete(): void
    {
        $user = $this->userWithRole('hr');
        $pending = $this->entry();
        $rejected = $this->entry(['date' => '2026-09-22']);

        $this->actingAs($user)->put(route('hr.time-entries.approve', $pending), ['manager_comments' => 'Fine'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $pending->fresh()?->status);
        $this->assertSame($user->id, $pending->fresh()->approved_by);

        $this->put(route('hr.time-entries.reject', $rejected))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $rejected->fresh()?->status);

        // Only pending entries can be decided on.
        $this->put(route('hr.time-entries.approve', $rejected))->assertSessionHasErrors('status');
        $this->assertSame('rejected', $rejected->fresh()?->status);

        $this->delete(route('hr.time-entries.destroy', $rejected))->assertRedirect();
        $this->assertModelMissing($rejected);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('hr.time-entries.index'))->assertRedirect(route('login'));
    }
}
