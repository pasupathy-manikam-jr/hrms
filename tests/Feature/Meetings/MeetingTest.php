<?php

namespace Tests\Feature\Meetings;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\MeetingRoom;
use App\Models\MeetingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_receives_every_meeting_in_date_order()
    {
        Meeting::factory()->count(3)->create(['status' => 'Completed', 'meeting_date' => '2026-09-01']);
        Meeting::factory()->create(['title' => 'Zeta Kickoff', 'meeting_date' => '2026-10-05']);

        $this->actingAs($this->userWithRole())
            ->get(route('meetings.meetings.index'))
            ->assertInertia(fn ($page) => $page
                ->component('meetings/meetings/index')
                ->has('meetings', 4)
                ->where('meetings.3.title', 'Zeta Kickoff')
                ->where('meetings.3.meeting_date', '2026-10-05')
                ->has('meetingTypes', 4)
                ->has('users'));
    }

    public function test_status_can_be_changed()
    {
        $meeting = Meeting::factory()->create(['status' => 'Scheduled']);
        $this->actingAs($this->userWithRole());

        $this->put(route('meetings.meetings.change-status', $meeting), ['status' => 'Postponed'])->assertSessionHasErrors('status');
        $this->put(route('meetings.meetings.change-status', $meeting), ['status' => 'In Progress'])->assertSessionHasNoErrors();
        $this->assertSame('In Progress', $meeting->fresh()->status);
    }

    public function test_meetings_can_be_created_updated_and_deleted()
    {
        $hr = $this->userWithRole('hr');
        $type = MeetingType::factory()->create();
        $room = MeetingRoom::factory()->create();
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->actingAs($hr);

        $this->post(route('meetings.meetings.store'), [
            'title' => '', 'type_id' => 999, 'meeting_date' => 'soon', 'start_time' => '10:00', 'end_time' => '09:00',
            'status' => 'Postponed', 'recurrence' => 'Yearly', 'organizer_id' => 999, 'attendee_ids' => [999],
        ])->assertSessionHasErrors(['title', 'type_id', 'meeting_date', 'end_time', 'status', 'recurrence', 'organizer_id', 'attendee_ids.0']);

        $payload = [
            'title' => 'Budget Sync', 'type_id' => $type->id, 'room_id' => $room->id, 'meeting_date' => '2026-10-01',
            'start_time' => '09:30', 'end_time' => '11:00', 'status' => 'Scheduled', 'recurrence' => 'None',
            'recurrence_end_date' => '2026-12-01', 'organizer_id' => $hr->id, 'attendee_ids' => [$alice->id, $bob->id],
        ];
        $this->post(route('meetings.meetings.store'), $payload)->assertSessionHasNoErrors();

        $meeting = Meeting::where('title', 'Budget Sync')->firstOrFail();
        $this->assertSame(90, $meeting->duration);
        $this->assertNull($meeting->recurrence_end_date);
        $this->assertEqualsCanonicalizing([$alice->id, $bob->id], $meeting->attendees->modelKeys());

        $this->put(route('meetings.meetings.update', $meeting), [
            ...$payload, 'title' => 'Budget Review', 'start_time' => '09:00:00', 'end_time' => '09:45:00',
            'recurrence' => 'Weekly', 'status' => 'Completed', 'attendee_ids' => [$bob->id],
        ])->assertSessionHasNoErrors();
        $meeting->refresh();
        $this->assertSame(['Budget Review', 45, 'Completed', '2026-12-01'], [$meeting->title, $meeting->duration, $meeting->status, $meeting->recurrence_end_date->toDateString()]);
        $this->assertSame([$bob->id], $meeting->attendees()->pluck('users.id')->all());

        $this->delete(route('meetings.meetings.destroy', $meeting));
        $this->assertModelMissing($meeting);
        $this->assertDatabaseCount('meeting_attendees', 0);
    }

    public function test_employees_only_see_meetings_they_organise_or_attend()
    {
        $employee = $this->userWithRole('employee');
        $organised = Meeting::factory()->create(['organizer_id' => $employee->id]);
        $attending = Meeting::factory()->create();
        $attending->attendees()->attach($employee);
        Meeting::factory()->count(2)->create();

        $this->actingAs($employee)
            ->get(route('meetings.meetings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('meetings', 2));

        $this->assertEqualsCanonicalizing(
            [$organised->id, $attending->id],
            Meeting::query()->visibleTo($employee)->pluck('id')->all(),
        );

        $this->actingAs($this->userWithRole('company'))
            ->get(route('meetings.meetings.index'))
            ->assertInertia(fn ($page) => $page->has('meetings', 4));
    }

    public function test_employees_cannot_create_edit_or_delete_meetings()
    {
        $employee = $this->userWithRole('employee');
        $meeting = Meeting::factory()->create(['organizer_id' => $employee->id]);
        $this->actingAs($employee);

        $this->post(route('meetings.meetings.store'), ['title' => 'X'])->assertForbidden();
        $this->put(route('meetings.meetings.update', $meeting), ['title' => 'X'])->assertForbidden();
        $this->delete(route('meetings.meetings.destroy', $meeting))->assertForbidden();
        $this->put(route('meetings.meetings.change-status', $meeting), ['status' => 'Cancelled'])->assertForbidden();
        $this->assertModelExists($meeting);
    }

    public function test_users_with_own_scope_cannot_edit_other_meetings()
    {
        $user = $this->userWithRole('employee')->givePermissionTo('delete-meetings');
        $meeting = Meeting::factory()->create();

        $this->actingAs($user)->delete(route('meetings.meetings.destroy', $meeting))->assertNotFound();
        $this->assertModelExists($meeting);
    }

    public function test_meeting_page_shows_attendees_minutes_and_action_items()
    {
        $this->withoutVite();
        $meeting = Meeting::factory()->create();
        $attendee = User::factory()->create();
        $meeting->attendees()->attach($attendee, ['type' => 'Required', 'rsvp_status' => 'Accepted', 'attendance_status' => 'Present']);
        MeetingMinute::create(['meeting_id' => $meeting->id, 'topic' => 'Sprint review', 'content' => 'Done', 'type' => 'Discussion', 'recorded_by' => $attendee->id, 'recorded_at' => now()]);
        ActionItem::factory()->create(['meeting_id' => $meeting->id, 'title' => 'Ship it', 'assigned_to' => $attendee->id]);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('meetings.meetings.show', $meeting))
            ->assertInertia(fn ($page) => $page
                ->component('meetings/meetings/show')
                ->where('meeting.id', $meeting->id)
                ->where('meeting.attendees.0.name', $attendee->name)
                ->where('meeting.attendees.0.pivot.rsvp_status', 'Accepted')
                ->where('minutes.0.topic', 'Sprint review')
                ->where('actionItems.0.title', 'Ship it')
                ->where('actionItems.0.assignee.name', $attendee->name));
    }

    public function test_employees_can_only_open_meetings_they_organise_or_attend()
    {
        $this->withoutVite();
        $employee = $this->userWithRole('employee');
        $attending = Meeting::factory()->create();
        $attending->attendees()->attach($employee);
        $other = Meeting::factory()->create();

        $this->actingAs($employee)->get(route('meetings.meetings.show', $attending))->assertOk();
        $this->get(route('meetings.meetings.show', $other))->assertNotFound();
    }
}
