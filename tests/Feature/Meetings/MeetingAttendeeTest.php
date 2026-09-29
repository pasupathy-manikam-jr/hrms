<?php

namespace Tests\Feature\Meetings;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingAttendeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_list_can_be_searched_and_filtered()
    {
        $meeting = Meeting::factory()->create();
        $meeting->attendees()->attach(User::factory()->create(['name' => 'Zara Quill']), ['rsvp_status' => 'Accepted', 'attendance_status' => 'Present']);
        $meeting->attendees()->attach(User::factory()->count(2)->create());

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('meetings.meeting-attendees.index', ['search' => 'Zara']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('meetings/meeting-attendees/index')
                ->has('meetingAttendees.data', 1)
                ->where('meetingAttendees.data.0.user.name', 'Zara Quill')
                ->where('meetingAttendees.data.0.type', 'Required'));

        $this->get(route('meetings.meeting-attendees.index', ['rsvp_status' => 'Pending']))
            ->assertInertia(fn ($page) => $page->has('meetingAttendees.data', 2));
        $this->get(route('meetings.meeting-attendees.index', ['attendance_status' => 'Present', 'meeting_id' => $meeting->id]))
            ->assertInertia(fn ($page) => $page->has('meetingAttendees.data', 1));
    }

    public function test_attendees_can_be_added_updated_and_removed()
    {
        $this->actingAs($this->userWithRole('hr'));
        $meeting = Meeting::factory()->create();
        [$alice, $bob] = User::factory()->count(2)->create();

        $this->post(route('meetings.meeting-attendees.store'), ['meeting_id' => 999, 'user_ids' => [999], 'type' => 'Maybe'])
            ->assertSessionHasErrors(['meeting_id', 'user_ids.0', 'type']);

        $this->post(route('meetings.meeting-attendees.store'), ['meeting_id' => $meeting->id, 'user_ids' => [$alice->id, $bob->id], 'type' => 'Optional'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Optional', 'Optional'], $meeting->attendees()->pluck('meeting_attendees.type')->all());

        $row = MeetingAttendee::where('user_id', $alice->id)->firstOrFail();
        $this->put(route('meetings.meeting-attendees.update', $row), ['rsvp_status' => 'Yes'])->assertSessionHasErrors('rsvp_status');
        $this->put(route('meetings.meeting-attendees.update', $row), [
            'type' => 'Required', 'rsvp_status' => 'Declined', 'attendance_status' => 'Late', 'decline_reason' => 'Clash',
        ])->assertSessionHasNoErrors();
        $row->refresh();
        $this->assertSame(['Required', 'Declined', 'Late', 'Clash', today()->toDateString()], [$row->type, $row->rsvp_status, $row->attendance_status, $row->decline_reason, $row->rsvp_date->toDateString()]);

        // The meeting form's sync keeps the RSVP details of attendees who stay.
        $meeting->attendees()->sync([$alice->id]);
        $this->assertSame('Declined', $row->fresh()->rsvp_status);

        $this->delete(route('meetings.meeting-attendees.destroy', $row));
        $this->assertModelMissing($row);
    }

    public function test_employees_see_and_rsvp_only_for_themselves()
    {
        // The real employee role: manage-own-meeting-attendees + edit-meeting-attendees.
        $user = $this->userWithRole('employee');
        $meeting = Meeting::factory()->create();
        $meeting->attendees()->attach([$user->id, User::factory()->create()->id]);
        [$mine, $theirs] = [MeetingAttendee::where('user_id', $user->id)->first(), MeetingAttendee::where('user_id', '!=', $user->id)->first()];

        $this->actingAs($user)
            ->get(route('meetings.meeting-attendees.index'))
            ->assertInertia(fn ($page) => $page->has('meetingAttendees.data', 1)->where('meetingAttendees.data.0.user_id', $user->id));

        // Only the RSVP fields are taken; type and attendance stay as the organiser set them.
        $this->put(route('meetings.meeting-attendees.update', $mine), ['type' => 'Optional', 'rsvp_status' => 'Accepted', 'attendance_status' => 'Present'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Required', 'Accepted', 'Not Attended'], [$mine->fresh()->type, $mine->fresh()->rsvp_status, $mine->fresh()->attendance_status]);

        $this->put(route('meetings.meeting-attendees.update', $theirs), ['rsvp_status' => 'Accepted'])->assertNotFound();
        $this->post(route('meetings.meeting-attendees.store'), ['meeting_id' => $meeting->id, 'user_ids' => [$user->id], 'type' => 'Required'])->assertForbidden();
        $this->delete(route('meetings.meeting-attendees.destroy', $mine))->assertForbidden();
    }
}
