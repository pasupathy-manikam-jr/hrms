<?php

namespace Tests\Feature\Meetings;

use App\Models\MeetingRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_type()
    {
        MeetingRoom::factory()->count(3)->create();
        MeetingRoom::factory()->create(['name' => 'Zoom Zeta', 'type' => 'Virtual']);

        $this->actingAs($this->userWithRole())
            ->get(route('meetings.meeting-rooms.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('meetings/meeting-rooms/index')
                ->has('meetingRooms.data', 1)
                ->where('meetingRooms.data.0.equipment', ['Whiteboard', 'WiFi']));

        $this->get(route('meetings.meeting-rooms.index', ['type' => 'Virtual']))
            ->assertInertia(fn ($page) => $page
                ->has('meetingRooms.data', 1)
                ->where('meetingRooms.data.0.name', 'Zoom Zeta'));
    }

    public function test_meeting_rooms_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('meetings.meeting-rooms.store'), ['name' => '', 'type' => 'Hologram', 'capacity' => 0, 'booking_url' => 'nope', 'status' => 'active'])
            ->assertSessionHasErrors(['name', 'type', 'capacity', 'booking_url']);
        $this->post(route('meetings.meeting-rooms.store'), [
            'name' => 'Loft', 'type' => 'Physical', 'capacity' => 8, 'equipment' => ['TV', 'WiFi'], 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $room = MeetingRoom::where('name', 'Loft')->firstOrFail();
        $this->assertSame(['TV', 'WiFi'], $room->equipment);

        $this->put(route('meetings.meeting-rooms.update', $room), [
            'name' => 'Loft 2', 'type' => 'Virtual', 'capacity' => 50, 'booking_url' => 'https://meet.example.com/loft', 'status' => 'inactive',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Virtual', $room->fresh()->type);

        $this->delete(route('meetings.meeting-rooms.destroy', $room));
        $this->assertModelMissing($room);
    }

    public function test_employees_cannot_manage_meeting_rooms()
    {
        $room = MeetingRoom::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('meetings.meeting-rooms.index'))->assertForbidden();
        $this->post(route('meetings.meeting-rooms.store'), ['name' => 'X', 'type' => 'Physical', 'capacity' => 2, 'status' => 'active'])->assertForbidden();
        $this->delete(route('meetings.meeting-rooms.destroy', $room))->assertForbidden();
        $this->assertModelExists($room);
    }
}
