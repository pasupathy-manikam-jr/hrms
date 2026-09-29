<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\Interview;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_has_status_counts_and_filters()
    {
        $job = JobPosting::factory()->create();
        Candidate::factory()->count(2)->create(['job_id' => $job->id]);
        Candidate::factory()->create(['first_name' => 'Zeta', 'status' => 'Interview']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.candidates.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/candidates/index')
                ->has('candidates.data', 3)
                ->has('candidates.data.0.job.title')
                ->has('sources', 3)
                ->where('statusCounts', ['all' => 3, 'New' => 2, 'Screening' => 0, 'Interview' => 1, 'Offer' => 0, 'Hired' => 0, 'Rejected' => 0]));

        $this->get(route('hr.recruitment.candidates.index', ['status' => 'Interview']))
            ->assertInertia(fn ($page) => $page->has('candidates.data', 1)->where('candidates.data.0.first_name', 'Zeta'));

        $this->get(route('hr.recruitment.candidates.index', ['job_id' => $job->id]))
            ->assertInertia(fn ($page) => $page->has('candidates.data', 2));
    }

    public function test_candidates_can_be_updated_through_the_pipeline_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));
        $candidate = Candidate::factory()->create();
        $payload = [
            'job_id' => $candidate->job_id,
            'source_id' => $candidate->source_id,
            'first_name' => 'Geeta',
            'last_name' => 'Devi',
            'email' => 'geeta@example.com',
            'experience_years' => 3,
            'date_of_birth' => '1991-02-28',
            'gender' => 'female',
            'status' => 'Screening',
        ];

        $this->put(route('hr.recruitment.candidates.update', $candidate), ['status' => 'Maybe', 'email' => 'nope', 'linkedin_url' => 'x'])
            ->assertSessionHasErrors(['job_id', 'first_name', 'last_name', 'email', 'experience_years', 'status', 'linkedin_url']);
        $this->put(route('hr.recruitment.candidates.update', $candidate), $payload)->assertSessionHasNoErrors();

        $candidate->refresh();
        $this->assertSame('Screening', $candidate->status);
        $this->assertSame('Geeta', $candidate->first_name);

        $this->delete(route('hr.recruitment.candidates.destroy', $candidate));
        $this->assertModelMissing($candidate);
    }

    public function test_deleting_a_job_posting_removes_its_candidates()
    {
        $candidate = Candidate::factory()->create();
        $this->actingAs($this->userWithRole())->delete(route('hr.recruitment.job-postings.destroy', $candidate->job_id));
        $this->assertModelMissing($candidate);
    }

    public function test_employees_cannot_manage_candidates()
    {
        $candidate = Candidate::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.candidates.index'))->assertForbidden();
        $this->put(route('hr.recruitment.candidates.update', $candidate), ['status' => 'Hired'])->assertForbidden();
        $this->delete(route('hr.recruitment.candidates.destroy', $candidate))->assertForbidden();
        $this->assertModelExists($candidate);
    }

    public function test_show_page_has_the_profile_interviews_assessments_and_offers()
    {
        $candidate = Candidate::factory()->create();
        $interview = Interview::factory()->create(['candidate_id' => $candidate->id]);
        $interviewer = User::factory()->create();
        $interview->interviewers()->attach($interviewer);
        Offer::factory()->create(['candidate_id' => $candidate->id]);
        CandidateAssessment::create(['candidate_id' => $candidate->id, 'assessment_name' => 'Coding test', 'assessment_date' => '2026-09-01', 'score' => 80, 'max_score' => 100]);
        Interview::factory()->create();

        $this->withoutVite()->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.candidates.show', $candidate))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/candidates/show')
                ->where('candidate.id', $candidate->id)
                ->has('candidate.job.title')
                ->has('interviews', 1)
                ->where('interviews.0.interviewers.0.name', $interviewer->name)
                ->has('offers', 1)
                ->where('assessments.0.pass_fail_status', 'Pass')
                ->where('onboarding', null));
    }

    public function test_show_page_is_scoped_to_own_candidates()
    {
        $this->userWithRole();
        $user = User::factory()->create()->givePermissionTo(['manage-candidates', 'manage-own-candidates', 'view-candidates']);
        $other = Candidate::factory()->create();
        $this->withoutVite()->actingAs($user);

        $this->get(route('hr.recruitment.candidates.show', $other))->assertNotFound();
        $this->get(route('hr.recruitment.candidates.show', Candidate::factory()->create(['created_by' => $user->id])))->assertOk();
        $this->actingAs($this->userWithRole('employee'))->get(route('hr.recruitment.candidates.show', Candidate::factory()->create()))->assertForbidden();
    }
}
