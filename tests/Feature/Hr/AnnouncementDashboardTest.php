<?php

namespace Tests\Feature\Hr;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_groups_announcements_and_applies_filters()
    {
        $branch = Branch::factory()->create();
        Announcement::factory()->create(['title' => 'Featured', 'category' => 'Events', 'is_featured' => true]);
        Announcement::factory()->create(['title' => 'Urgent', 'category' => 'Benefits', 'is_high_priority' => true]);
        Announcement::factory()->create(['title' => 'Soon', 'category' => 'Events', 'start_date' => today()->addWeek(), 'end_date' => null])
            ->branches()->attach($branch);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.announcements.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/announcements/dashboard')
                ->has('allAnnouncements', 3)
                ->has('featuredAnnouncements', 1)
                ->where('featuredAnnouncements.0.title', 'Featured')
                ->has('highPriorityAnnouncements', 1)
                ->where('highPriorityAnnouncements.0.title', 'Urgent')
                ->has('upcomingAnnouncements', 1)
                ->where('upcomingAnnouncements.0.status', 'upcoming')
                ->where('categories', Announcement::CATEGORIES)
                ->has('departments')
                ->has('branches', 1));

        $this->get(route('hr.announcements.dashboard', ['category' => 'Events']))
            ->assertInertia(fn ($page) => $page->has('allAnnouncements', 2)->has('highPriorityAnnouncements', 0)->where('filters.category', 'Events'));
        $this->get(route('hr.announcements.dashboard', ['branch_id' => $branch->id]))
            ->assertInertia(fn ($page) => $page->has('allAnnouncements', 1)->where('allAnnouncements.0.title', 'Soon'));
    }

    public function test_without_manage_any_only_visible_announcements_are_shown()
    {
        $this->userWithRole(); // seeds the permissions
        $viewer = User::factory()->create()->givePermissionTo(['manage-announcements', 'manage-own-announcements']);
        Announcement::factory()->create(['title' => 'For everyone', 'is_featured' => true]);
        Announcement::factory()->create(['title' => 'Finance only', 'is_company_wide' => false, 'is_featured' => true]);

        $this->actingAs($viewer)
            ->get(route('hr.announcements.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('allAnnouncements', 1)
                ->has('featuredAnnouncements', 1)
                ->where('featuredAnnouncements.0.title', 'For everyone'));
    }

    public function test_users_without_manage_announcements_are_denied()
    {
        $this->userWithRole();

        $this->actingAs(User::factory()->create())
            ->get(route('hr.announcements.dashboard'))
            ->assertForbidden();
    }
}
