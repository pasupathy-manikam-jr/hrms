<?php

namespace Tests\Feature\Hr;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        Announcement::factory()->count(3)->create();
        Announcement::factory()->create(['title' => 'Picnic Day', 'category' => 'Events', 'is_featured' => true, 'start_date' => today()->addWeek(), 'end_date' => null]);
        Announcement::factory()->create(['title' => 'Old Memo', 'start_date' => '2020-01-01', 'end_date' => '2020-01-31']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.announcements.index', ['search' => 'Picnic']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/announcements/index')
                ->has('announcements.data', 1)
                ->where('announcements.data.0.status', 'upcoming')
                ->where('announcements.data.0.start_date', today()->addWeek()->toDateString())
                ->has('categories', count(Announcement::CATEGORIES)));

        $this->get(route('hr.announcements.index', ['status' => 'expired']))
            ->assertInertia(fn ($page) => $page->has('announcements.data', 1)->where('announcements.data.0.title', 'Old Memo'));
        $this->get(route('hr.announcements.index', ['status' => 'active']))
            ->assertInertia(fn ($page) => $page->has('announcements.data', 3));
        $this->get(route('hr.announcements.index', ['featured' => 'yes', 'category' => 'Events']))
            ->assertInertia(fn ($page) => $page->has('announcements.data', 1)->where('filters.featured', 'yes'));
        $this->get(route('hr.announcements.index', ['date_from' => '2019-12-01', 'date_to' => '2020-02-01']))
            ->assertInertia(fn ($page) => $page->has('announcements.data', 1));
    }

    public function test_announcements_can_be_created_targeted_updated_and_deleted()
    {
        Storage::fake('local');
        $department = Department::factory()->create();
        $branch = Branch::factory()->create();
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.announcements.store'), ['title' => '', 'category' => 'Gossip', 'start_date' => '2026-02-01', 'end_date' => '2026-01-01', 'is_company_wide' => false])
            ->assertSessionHasErrors(['title', 'category', 'description', 'content', 'end_date', 'department_ids']);

        $this->post(route('hr.announcements.store'), [
            'title' => 'Server move',
            'category' => 'IT Updates',
            'description' => 'Data centre move.',
            'content' => 'Downtime on Saturday.',
            'document' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'),
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'is_high_priority' => true,
            'is_company_wide' => false,
            'department_ids' => [$department->id],
            'branch_ids' => [$branch->id],
        ])->assertSessionHasNoErrors();

        $announcement = Announcement::where('title', 'Server move')->firstOrFail();
        $this->assertSame($user->id, $announcement->created_by);
        $this->assertEquals([$department->id], $announcement->departments->modelKeys());
        $this->assertEquals([$branch->id], $announcement->branches->modelKeys());
        $this->get(route('hr.announcements.document', $announcement))->assertDownload('plan.pdf');

        $this->put(route('hr.announcements.update', $announcement), [
            'title' => 'Server move (updated)',
            'category' => 'IT Updates',
            'description' => 'Data centre move.',
            'content' => 'Downtime on Sunday.',
            'start_date' => '2026-10-01',
            'end_date' => null,
            'is_company_wide' => true,
            'department_ids' => [$department->id],
        ])->assertSessionHasNoErrors();

        $announcement->refresh();
        $this->assertSame('Server move (updated)', $announcement->title);
        $this->assertNull($announcement->end_date);
        $this->assertCount(0, $announcement->departments);
        $this->assertCount(0, $announcement->branches);

        $this->delete(route('hr.announcements.destroy', $announcement));
        $this->assertModelMissing($announcement);
    }

    public function test_employees_can_view_but_not_change_announcements()
    {
        $announcement = Announcement::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.announcements.index'))->assertOk()->assertInertia(fn ($page) => $page->has('announcements.data', 1));
        $this->post(route('hr.announcements.store'), ['title' => 'X'])->assertForbidden();
        $this->put(route('hr.announcements.update', $announcement), ['title' => 'X'])->assertForbidden();
        $this->delete(route('hr.announcements.destroy', $announcement))->assertForbidden();
        $this->assertModelExists($announcement);
    }

    public function test_without_manage_any_only_company_wide_and_own_announcements_are_listed()
    {
        $this->userWithRole(); // seeds the permissions
        $viewer = User::factory()->create()->givePermissionTo(['manage-announcements', 'view-announcements']);

        Announcement::factory()->create(['title' => 'For everyone']);
        Announcement::factory()->create(['title' => 'Mine', 'is_company_wide' => false, 'created_by' => $viewer->id]);
        Announcement::factory()->create(['title' => 'Finance only', 'is_company_wide' => false]);

        $this->actingAs($viewer)
            ->get(route('hr.announcements.index', ['sort_field' => 'title', 'sort_direction' => 'asc']))
            ->assertInertia(fn ($page) => $page
                ->has('announcements.data', 2)
                ->where('announcements.data.0.title', 'For everyone')
                ->where('announcements.data.1.title', 'Mine'));
    }

    public function test_announcement_page_shows_content_and_audience()
    {
        $this->withoutVite();
        $department = Department::factory()->create();
        $announcement = Announcement::factory()->create(['is_company_wide' => false]);
        $announcement->departments()->attach($department);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.announcements.show', $announcement))
            ->assertInertia(fn ($page) => $page
                ->component('hr/announcements/show')
                ->where('announcement.id', $announcement->id)
                ->where('announcement.content', $announcement->content)
                ->where('announcement.departments.0.name', $department->name)
                ->has('announcement.branches', 0));
    }

    public function test_without_manage_any_targeted_announcements_for_others_return_404()
    {
        $this->withoutVite();
        $this->userWithRole(); // seeds the permissions
        $viewer = User::factory()->create()->givePermissionTo(['manage-announcements', 'view-announcements']);
        $others = Announcement::factory()->create(['is_company_wide' => false]);
        $everyone = Announcement::factory()->create();

        $this->actingAs($viewer)->get(route('hr.announcements.show', $others))->assertNotFound();
        $this->get(route('hr.announcements.show', $everyone))->assertOk();
    }

    public function test_opening_an_announcement_counts_a_view_in_its_statistics()
    {
        $this->withoutVite();
        $department = Department::factory()->create();
        $reader = Employee::factory()->create(['department_id' => $department->id]);
        Employee::factory()->create(['department_id' => $department->id]);
        Employee::factory()->create(); // outside the audience
        $announcement = Announcement::factory()->create(['is_company_wide' => false]);
        $announcement->departments()->attach($department);
        $this->userWithRole(); // seeds the permissions
        $reader->user->givePermissionTo('manage-announcements');

        $this->actingAs($reader->user)->get(route('hr.announcements.show', $announcement))->assertOk();
        $this->get(route('hr.announcements.show', $announcement))->assertOk(); // a second visit is not a new view

        $this->actingAs($this->userWithRole())
            ->get(route('hr.announcements.statistics', $announcement))
            ->assertInertia(fn ($page) => $page
                ->component('hr/announcements/statistics')
                ->where('totalEmployees', 2)
                ->where('viewCount', 1)
                ->where('viewPercentage', 50));
    }
}
