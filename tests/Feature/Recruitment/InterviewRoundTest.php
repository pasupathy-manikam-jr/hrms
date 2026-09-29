<?php

namespace Tests\Feature\Recruitment;

use App\Models\InterviewRound;
use App\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewRoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_filtered_by_job_and_status()
    {
        $job = JobPosting::factory()->create();
        InterviewRound::factory()->count(2)->create();
        InterviewRound::factory()->create(['job_id' => $job->id, 'name' => 'Culture Chat', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.interview-rounds.index', ['job_id' => $job->id]))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/interview-rounds/index')
                ->has('interviewRounds.data', 1)
                ->where('interviewRounds.data.0.name', 'Culture Chat')
                ->where('interviewRounds.data.0.job.id', $job->id)
                ->where('statusCounts', ['all' => 3, 'active' => 2, 'inactive' => 1]));
    }

    public function test_rounds_can_be_created_updated_and_deleted_with_a_unique_sequence_per_job()
    {
        $job = JobPosting::factory()->create();
        $this->actingAs($this->userWithRole());
        $payload = ['job_id' => $job->id, 'name' => 'Screening', 'sequence_number' => 1, 'status' => 'active'];

        $this->post(route('hr.recruitment.interview-rounds.store'), ['name' => '', 'status' => 'x'])
            ->assertSessionHasErrors(['job_id', 'name', 'sequence_number', 'status']);
        $this->post(route('hr.recruitment.interview-rounds.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('hr.recruitment.interview-rounds.store'), $payload)->assertSessionHasErrors('sequence_number');

        $round = InterviewRound::where('name', 'Screening')->firstOrFail();
        $this->put(route('hr.recruitment.interview-rounds.update', $round), ['name' => 'Phone Screen'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('Phone Screen', $round->fresh()->name);

        $this->delete(route('hr.recruitment.interview-rounds.destroy', $round));
        $this->assertModelMissing($round);
    }

    public function test_employees_can_view_but_not_manage_rounds()
    {
        $round = InterviewRound::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.interview-rounds.index'))->assertInertia(fn ($page) => $page->has('interviewRounds.data', 1));
        $this->post(route('hr.recruitment.interview-rounds.store'), [])->assertForbidden();
        $this->delete(route('hr.recruitment.interview-rounds.destroy', $round))->assertForbidden();
        $this->put(route('hr.recruitment.interview-rounds.toggle-status', $round))->assertForbidden();
        $this->assertModelExists($round);
    }

    public function test_status_can_be_toggled()
    {
        $round = InterviewRound::factory()->create(['status' => 'active']);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.recruitment.interview-rounds.toggle-status', $round))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $round->fresh()->status);

        $this->put(route('hr.recruitment.interview-rounds.toggle-status', $round));
        $this->assertSame('active', $round->fresh()->status);
    }
}
