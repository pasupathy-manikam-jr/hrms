<?php

namespace Tests\Feature\Recruitment;

use App\Models\ChecklistItem;
use App\Models\OnboardingChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_item_counts_and_can_be_filtered()
    {
        $standard = OnboardingChecklist::create(['name' => 'Standard Onboarding', 'is_default' => true]);
        ChecklistItem::create(['checklist_id' => $standard->id, 'task_name' => 'Sign contract', 'category' => 'HR']);
        OnboardingChecklist::create(['name' => 'Intern Onboarding', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.recruitment.onboarding-checklists.index', ['is_default' => '1']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/onboarding-checklists/index')
                ->has('onboardingChecklists.data', 1)
                ->where('onboardingChecklists.data.0.checklist_items_count', 1)
                ->where('statusCounts', ['all' => 1, 'active' => 1, 'inactive' => 0]));

        $this->get(route('hr.recruitment.onboarding-checklists.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('onboardingChecklists.data', 1)->where('onboardingChecklists.data.0.name', 'Intern Onboarding'));
    }

    public function test_checklists_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);
        $previousDefault = OnboardingChecklist::create(['name' => 'Old default', 'is_default' => true]);

        $this->post(route('hr.recruitment.onboarding-checklists.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.recruitment.onboarding-checklists.store'), ['name' => 'Sales Team', 'is_default' => true, 'status' => 'active'])->assertSessionHasNoErrors();

        $record = OnboardingChecklist::where('name', 'Sales Team')->firstOrFail();
        $this->assertSame($user->id, $record->created_by);
        $this->assertTrue($record->is_default);
        $this->assertFalse($previousDefault->fresh()->is_default, 'Only one checklist is the default.');

        $this->put(route('hr.recruitment.onboarding-checklists.update', $record), ['name' => 'Sales Onboarding', 'is_default' => false, 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('Sales Onboarding', $record->fresh()->name);

        ChecklistItem::create(['checklist_id' => $record->id, 'task_name' => 'CRM access', 'category' => 'IT Setup']);
        $this->put(route('hr.recruitment.onboarding-checklists.toggle-status', $record))->assertSessionHasNoErrors();
        $this->assertSame('active', $record->fresh()->status);

        $this->delete(route('hr.recruitment.onboarding-checklists.destroy', $record));
        $this->assertModelMissing($record);
        $this->assertSame(0, ChecklistItem::count(), 'Items go with their checklist.');
    }

    public function test_employees_cannot_manage_checklists()
    {
        $record = OnboardingChecklist::create(['name' => 'Standard']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.onboarding-checklists.index'))->assertForbidden();
        $this->post(route('hr.recruitment.onboarding-checklists.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.recruitment.onboarding-checklists.toggle-status', $record))->assertForbidden();
        $this->delete(route('hr.recruitment.onboarding-checklists.destroy', $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_manage_own_users_only_see_their_own_records()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-onboarding-checklists', 'manage-own-onboarding-checklists', 'edit-onboarding-checklists']);
        $mine = OnboardingChecklist::create(['name' => 'Mine', 'created_by' => $user->id]);
        $other = OnboardingChecklist::create(['name' => 'Other']);

        $this->actingAs($user)
            ->get(route('hr.recruitment.onboarding-checklists.index'))
            ->assertInertia(fn ($page) => $page->has('onboardingChecklists.data', 1)->where('onboardingChecklists.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.onboarding-checklists.update', $other), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
