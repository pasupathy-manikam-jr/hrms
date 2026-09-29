<?php

namespace Tests\Feature\Meetings;

use App\Models\Meeting;
use App\Models\MeetingMinute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingMinuteTest extends TestCase
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
        MeetingMinute::create(['meeting_id' => $meeting->id, 'topic' => 'Budget freeze', 'content' => 'Agreed to freeze.', 'type' => 'Decision']);
        MeetingMinute::create(['meeting_id' => $meeting->id, 'topic' => 'Standup notes', 'content' => 'All on track.', 'type' => 'Note']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('meetings.meeting-minutes.index', ['search' => 'freeze']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('meetings/meeting-minutes/index')
                ->has('meetingMinutes.data', 1)
                ->where('meetingMinutes.data.0.type', 'Decision'));

        $this->get(route('meetings.meeting-minutes.index', ['type' => 'Note', 'meeting_id' => $meeting->id]))
            ->assertInertia(fn ($page) => $page->has('meetingMinutes.data', 1)->where('meetingMinutes.data.0.topic', 'Standup notes'));
    }

    public function test_minutes_can_be_created_updated_and_deleted()
    {
        $hr = $this->userWithRole('hr');
        $this->actingAs($hr);
        $meeting = Meeting::factory()->create();

        $this->post(route('meetings.meeting-minutes.store'), ['meeting_id' => 999, 'topic' => '', 'content' => '', 'type' => 'Gossip'])
            ->assertSessionHasErrors(['meeting_id', 'topic', 'content', 'type']);

        $this->post(route('meetings.meeting-minutes.store'), ['meeting_id' => $meeting->id, 'topic' => 'Hiring', 'content' => 'Open two roles.', 'type' => 'Decision'])
            ->assertSessionHasNoErrors();
        $minute = MeetingMinute::where('topic', 'Hiring')->firstOrFail();
        $this->assertSame($hr->id, $minute->recorded_by);
        $this->assertNotNull($minute->recorded_at);

        $this->put(route('meetings.meeting-minutes.update', $minute), ['meeting_id' => $meeting->id, 'topic' => 'Hiring plan', 'content' => 'Open three roles.', 'type' => 'Action Item'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Hiring plan', 'Action Item'], [$minute->fresh()->topic, $minute->fresh()->type]);

        $this->delete(route('meetings.meeting-minutes.destroy', $minute));
        $this->assertModelMissing($minute);
    }

    public function test_employees_only_see_minutes_of_meetings_they_can_see()
    {
        $employee = $this->userWithRole('employee');
        $attending = Meeting::factory()->create();
        $attending->attendees()->attach($employee);
        $hidden = Meeting::factory()->create();
        MeetingMinute::create(['meeting_id' => $attending->id, 'topic' => 'Mine', 'content' => 'x', 'type' => 'Note']);
        $other = MeetingMinute::create(['meeting_id' => $hidden->id, 'topic' => 'Theirs', 'content' => 'x', 'type' => 'Note']);

        $this->actingAs($employee)
            ->get(route('meetings.meeting-minutes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('meetingMinutes.data', 1)
                ->where('meetingMinutes.data.0.topic', 'Mine')
                ->has('meetings', 1));

        $this->post(route('meetings.meeting-minutes.store'), ['meeting_id' => $attending->id, 'topic' => 'X', 'content' => 'x', 'type' => 'Note'])->assertForbidden();

        $employee->givePermissionTo(['create-meeting-minutes', 'delete-meeting-minutes']);
        $this->post(route('meetings.meeting-minutes.store'), ['meeting_id' => $hidden->id, 'topic' => 'X', 'content' => 'x', 'type' => 'Note'])->assertNotFound();
        $this->delete(route('meetings.meeting-minutes.destroy', $other))->assertNotFound();
        $this->assertModelExists($other);
    }
}
