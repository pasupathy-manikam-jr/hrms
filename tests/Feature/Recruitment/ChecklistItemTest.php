<?php

namespace Tests\Feature\Recruitment;

use App\Models\ChecklistItem;
use App\Models\OnboardingChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistItemTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $attributes = []): ChecklistItem
    {
        return ChecklistItem::create($attributes + [
            'checklist_id' => OnboardingChecklist::create(['name' => 'Standard'])->id,
            'task_name' => 'Complete I-9 form',
            'category' => 'Documentation',
            'due_day' => 1,
        ]);
    }

    public function test_list_can_be_filtered_by_checklist_category_and_required()
    {
        $laptop = $this->item(['task_name' => 'Laptop setup', 'category' => 'IT Setup', 'is_required' => false]);
        $this->item();

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.recruitment.checklist-items.index', ['category' => 'IT Setup']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/checklist-items/index')
                ->has('checklistItems.data', 1)
                ->where('checklistItems.data.0.task_name', 'Laptop setup')
                ->where('checklistItems.data.0.checklist.name', 'Standard')
                ->has('checklists', 2));

        $this->get(route('hr.recruitment.checklist-items.index', ['checklist_id' => $laptop->checklist_id]))
            ->assertInertia(fn ($page) => $page->has('checklistItems.data', 1));
        $this->get(route('hr.recruitment.checklist-items.index', ['is_required' => '1']))
            ->assertInertia(fn ($page) => $page->has('checklistItems.data', 1)->where('checklistItems.data.0.task_name', 'Complete I-9 form'));
    }

    public function test_items_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $checklist = OnboardingChecklist::create(['name' => 'Standard']);
        $this->actingAs($user);

        $this->post(route('hr.recruitment.checklist-items.store'), ['checklist_id' => 999, 'task_name' => '', 'category' => 'Bogus', 'due_day' => -1])
            ->assertSessionHasErrors(['checklist_id', 'task_name', 'category', 'due_day']);

        $this->post(route('hr.recruitment.checklist-items.store'), [
            'checklist_id' => $checklist->id, 'task_name' => 'Security badge', 'category' => 'Facilities',
            'assigned_to_role' => 'Facilities', 'due_day' => 1, 'is_required' => true, 'sort_order' => 3,
        ])->assertSessionHasNoErrors();

        $record = ChecklistItem::where('task_name', 'Security badge')->firstOrFail();
        $this->assertSame($user->id, $record->created_by);
        $this->assertSame(3, $record->sort_order);

        $this->put(route('hr.recruitment.checklist-items.update', $record), [
            'checklist_id' => $checklist->id, 'task_name' => 'Access card', 'category' => 'Facilities', 'due_day' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Access card', $record->fresh()->task_name);

        $this->put(route('hr.recruitment.checklist-items.toggle-status', $record))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $record->fresh()->status);

        $this->delete(route('hr.recruitment.checklist-items.destroy', $record));
        $this->assertModelMissing($record);
    }

    public function test_employees_cannot_manage_checklist_items()
    {
        $record = $this->item();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.checklist-items.index'))->assertForbidden();
        $this->post(route('hr.recruitment.checklist-items.store'), [])->assertForbidden();
        $this->put(route('hr.recruitment.checklist-items.toggle-status', $record))->assertForbidden();
        $this->delete(route('hr.recruitment.checklist-items.destroy', $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_manage_own_users_only_see_their_own_records()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-checklist-items', 'manage-own-checklist-items', 'edit-checklist-items']);
        $mine = $this->item(['created_by' => $user->id]);
        $other = $this->item();

        $this->actingAs($user)
            ->get(route('hr.recruitment.checklist-items.index'))
            ->assertInertia(fn ($page) => $page->has('checklistItems.data', 1)->where('checklistItems.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.checklist-items.update', $other), [
            'checklist_id' => $other->checklist_id, 'task_name' => 'X', 'category' => 'HR', 'due_day' => 0,
        ])->assertForbidden();
    }
}
