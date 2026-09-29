<?php

namespace Tests\Feature\Meetings;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_filtered_and_counts_statuses()
    {
        ActionItem::factory()->count(2)->create(['status' => 'Overdue']);
        ActionItem::factory()->create(['title' => 'Zeta Report', 'priority' => 'Critical']);

        $this->actingAs($this->userWithRole())
            ->get(route('meetings.action-items.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('meetings/action-items/index')
                ->has('actionItems.data', 1)
                ->where('actionItems.data.0.priority', 'Critical')
                ->where('statusCounts', ['all' => 3, 'Not Started' => 1, 'In Progress' => 0, 'Completed' => 0, 'Overdue' => 2])
                ->has('meetings', 3));

        $this->get(route('meetings.action-items.index', ['status' => 'Overdue']))
            ->assertInertia(fn ($page) => $page->has('actionItems.data', 2));
    }

    public function test_action_items_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));
        $meeting = Meeting::factory()->create();
        $assignee = User::factory()->create();

        $this->post(route('meetings.action-items.store'), [
            'meeting_id' => 999, 'title' => '', 'assigned_to' => 999, 'due_date' => 'tomorrow',
            'priority' => 'Whenever', 'status' => 'Done', 'progress_percentage' => 150,
        ])->assertSessionHasErrors(['meeting_id', 'title', 'assigned_to', 'due_date', 'priority', 'status', 'progress_percentage']);

        $payload = [
            'meeting_id' => $meeting->id, 'title' => 'Draft minutes', 'assigned_to' => $assignee->id, 'due_date' => '2026-10-10',
            'priority' => 'High', 'status' => 'In Progress', 'progress_percentage' => 40, 'completed_date' => '2026-10-09',
        ];
        $this->post(route('meetings.action-items.store'), $payload)->assertSessionHasNoErrors();

        $item = ActionItem::where('title', 'Draft minutes')->firstOrFail();
        $this->assertNull($item->completed_date);

        $this->put(route('meetings.action-items.update', $item), [...$payload, 'status' => 'Completed', 'progress_percentage' => 100, 'completed_date' => null])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Completed', today()->toDateString()], [$item->fresh()->status, $item->fresh()->completed_date->toDateString()]);

        $this->delete(route('meetings.action-items.destroy', $item));
        $this->assertModelMissing($item);

        // Deleting a meeting removes its action items.
        $other = ActionItem::factory()->create(['meeting_id' => $meeting->id]);
        $meeting->delete();
        $this->assertModelMissing($other);
    }

    public function test_employees_only_see_items_assigned_to_them_or_from_their_meetings()
    {
        $employee = $this->userWithRole('employee');
        ActionItem::factory()->create(['assigned_to' => $employee->id]);
        ActionItem::factory()->create(['meeting_id' => Meeting::factory()->create(['organizer_id' => $employee->id])->id]);
        ActionItem::factory()->count(3)->create();

        $this->actingAs($employee)
            ->get(route('meetings.action-items.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('actionItems.data', 2)
                ->where('statusCounts.all', 2)
                ->has('meetings', 1));

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('meetings.action-items.index'))
            ->assertInertia(fn ($page) => $page->has('actionItems.data', 5));
    }

    public function test_employees_cannot_create_edit_or_delete_action_items()
    {
        $employee = $this->userWithRole('employee');
        $item = ActionItem::factory()->create(['assigned_to' => $employee->id]);
        $this->actingAs($employee);

        $this->post(route('meetings.action-items.store'), ['title' => 'X'])->assertForbidden();
        $this->put(route('meetings.action-items.update', $item), ['title' => 'X'])->assertForbidden();
        $this->delete(route('meetings.action-items.destroy', $item))->assertForbidden();
        $this->assertModelExists($item);

        $employee->givePermissionTo('delete-action-items');
        $this->delete(route('meetings.action-items.destroy', ActionItem::factory()->create()))->assertNotFound();
    }

    public function test_progress_updates_move_the_status_along()
    {
        $item = ActionItem::factory()->create(['status' => 'Not Started', 'progress_percentage' => 0]);
        $this->actingAs($this->userWithRole());

        $this->put(route('meetings.action-items.progress', $item), ['progress_percentage' => 101])->assertSessionHasErrors('progress_percentage');

        $this->put(route('meetings.action-items.progress', $item), ['progress_percentage' => 40, 'notes' => 'Draft ready'])->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame(['In Progress', 40, 'Draft ready'], [$item->status, $item->progress_percentage, $item->notes]);
        $this->assertNull($item->completed_date);

        $this->put(route('meetings.action-items.progress', $item), ['progress_percentage' => 100]);
        $item->refresh();
        $this->assertSame('Completed', $item->status);
        $this->assertNotNull($item->completed_date);

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('meetings.action-items.progress', $item), ['progress_percentage' => 10])
            ->assertForbidden();
    }
}
