<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\JobCategory;
use App\Models\JobLocation;
use App\Models\JobPosting;
use App\Models\JobType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_has_status_counts_filters_and_relations()
    {
        JobPosting::factory()->count(2)->create();
        JobPosting::factory()->create(['title' => 'Zeta Engineer', 'status' => 'Published']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.job-postings.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/job-postings/index')
                ->has('jobPostings.data', 3)
                ->has('jobPostings.data.0.job_type.name')
                ->has('departments', 3)
                ->where('statusCounts', ['all' => 3, 'Draft' => 2, 'Published' => 1, 'Closed' => 0]));

        $this->get(route('hr.recruitment.job-postings.index', ['status' => 'Published']))
            ->assertInertia(fn ($page) => $page
                ->has('jobPostings.data', 1)
                ->where('jobPostings.data.0.title', 'Zeta Engineer')
                ->where('jobPostings.data.0.is_published', true));
    }

    public function test_job_postings_can_be_created_updated_published_and_deleted()
    {
        $this->actingAs($this->userWithRole());
        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $payload = [
            'title' => 'Backend Developer',
            'job_category_id' => JobCategory::factory()->create()->id,
            'job_type_id' => JobType::factory()->create()->id,
            'location_id' => JobLocation::factory()->create()->id,
            'branch_id' => $department->branch_id,
            'department_id' => $department->id,
            'positions' => 2,
            'min_experience' => 2,
            'max_experience' => 5,
            'min_salary' => 50000,
            'max_salary' => 80000,
            'start_date' => '2026-11-01',
            'application_deadline' => '2026-10-15',
            'skills' => 'PHP, Laravel , ,MySQL',
            'priority' => 'High',
            'is_featured' => true,
            'status' => 'Draft',
        ];

        $this->post(route('hr.recruitment.job-postings.store'), ['title' => '', 'status' => 'bogus', 'priority' => 'x'])
            ->assertSessionHasErrors(['title', 'job_type_id', 'location_id', 'branch_id', 'department_id', 'positions', 'status', 'priority']);
        $this->post(route('hr.recruitment.job-postings.store'), ['department_id' => $otherDepartment->id, 'max_salary' => 1] + $payload)
            ->assertSessionHasErrors(['department_id', 'max_salary']);
        $this->post(route('hr.recruitment.job-postings.store'), $payload)->assertSessionHasNoErrors();

        $posting = JobPosting::where('title', 'Backend Developer')->firstOrFail();
        $this->assertSame(['PHP', 'Laravel', 'MySQL'], $posting->skills);
        $this->assertSame(sprintf('JOB-%05d', $posting->id), $posting->job_code);
        $this->assertFalse($posting->is_published);

        $this->put(route('hr.recruitment.job-postings.update', $posting), ['title' => 'Senior Backend Developer', 'status' => 'Closed'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('Senior Backend Developer', $posting->fresh()->title);

        $this->put(route('hr.recruitment.job-postings.publish', $posting));
        $posting->refresh();
        $this->assertSame('Published', $posting->status);
        $this->assertTrue($posting->is_published);
        $this->assertNotNull($posting->publish_date);

        $this->put(route('hr.recruitment.job-postings.publish', $posting));
        $this->assertSame('Draft', $posting->fresh()->status);

        $this->delete(route('hr.recruitment.job-postings.destroy', $posting));
        $this->assertModelMissing($posting);
    }

    public function test_employees_can_view_but_not_manage_job_postings()
    {
        $posting = JobPosting::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.job-postings.index'))->assertOk();
        $this->post(route('hr.recruitment.job-postings.store'), ['title' => 'X'])->assertForbidden();
        $this->put(route('hr.recruitment.job-postings.publish', $posting))->assertForbidden();
        $this->delete(route('hr.recruitment.job-postings.destroy', $posting))->assertForbidden();
        $this->assertModelExists($posting);
    }

    public function test_show_page_has_the_posting_and_its_candidates()
    {
        $posting = JobPosting::factory()->create();
        Candidate::factory()->count(2)->create(['job_id' => $posting->id]);
        $this->withoutVite()->actingAs($this->userWithRole());

        $this->get(route('hr.recruitment.job-postings.show', $posting))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/job-postings/show')
                ->where('jobPosting.id', $posting->id)
                ->has('jobPosting.job_type.name')
                ->has('candidates', 2));

        // Employees may browse postings but not see who applied.
        $this->actingAs($this->userWithRole('employee'))
            ->get(route('hr.recruitment.job-postings.show', $posting))
            ->assertInertia(fn ($page) => $page->where('candidates', null));
    }

    public function test_show_page_is_scoped_to_own_postings()
    {
        $this->userWithRole();
        $user = User::factory()->create()->givePermissionTo(['manage-job-postings', 'manage-own-job-postings', 'view-job-postings']);
        $other = JobPosting::factory()->create();
        $this->withoutVite()->actingAs($user);

        $this->get(route('hr.recruitment.job-postings.show', $other))->assertNotFound();
        $this->get(route('hr.recruitment.job-postings.show', JobPosting::factory()->create(['created_by' => $user->id])))->assertOk();
    }
}
