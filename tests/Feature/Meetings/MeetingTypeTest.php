<?php

namespace Tests\Feature\Meetings;

use App\Models\Meeting;
use App\Models\MeetingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_filtered_and_counts_meetings()
    {
        $type = MeetingType::factory()->create(['name' => 'Zeta Sync']);
        Meeting::factory()->count(2)->create(['type_id' => $type->id]);
        MeetingType::factory()->create(['name' => 'Old Format', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('meetings.meeting-types.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('meetings/meeting-types/index')
                ->has('meetingTypes.data', 1)
                ->where('meetingTypes.data.0.meetings_count', 2));

        $this->get(route('meetings.meeting-types.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page
                ->has('meetingTypes.data', 1)
                ->where('meetingTypes.data.0.name', 'Old Format'));
    }

    public function test_meeting_types_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('meetings.meeting-types.store'), ['name' => '', 'color' => 'red', 'default_duration' => 0, 'status' => 'x'])
            ->assertSessionHasErrors(['name', 'color', 'default_duration', 'status']);
        $this->post(route('meetings.meeting-types.store'), ['name' => 'Retro', 'color' => '#112233', 'default_duration' => 45, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $type = MeetingType::where('name', 'Retro')->firstOrFail();

        $this->put(route('meetings.meeting-types.update', $type), ['name' => 'Sprint Retro', 'color' => '#112233', 'default_duration' => 30, 'status' => 'inactive'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Sprint Retro', 30, 'inactive'], [$type->fresh()->name, $type->fresh()->default_duration, $type->fresh()->status]);

        $meeting = Meeting::factory()->create(['type_id' => $type->id]);
        $this->delete(route('meetings.meeting-types.destroy', $type));
        $this->assertModelMissing($type);
        $this->assertNull($meeting->fresh()->type_id);
    }

    public function test_employees_cannot_manage_meeting_types()
    {
        $type = MeetingType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('meetings.meeting-types.index'))->assertForbidden();
        $this->post(route('meetings.meeting-types.store'), ['name' => 'X', 'color' => '#000000', 'default_duration' => 30, 'status' => 'active'])->assertForbidden();
        $this->delete(route('meetings.meeting-types.destroy', $type))->assertForbidden();
        $this->assertModelExists($type);
    }

    public function test_meeting_type_can_be_locked_and_unlocked()
    {
        $type = MeetingType::factory()->create(['status' => 'active']);
        $this->actingAs($this->userWithRole());

        $this->put(route('meetings.meeting-types.toggle-status', $type))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $type->fresh()->status);

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('meetings.meeting-types.toggle-status', $type))
            ->assertForbidden();
    }
}
