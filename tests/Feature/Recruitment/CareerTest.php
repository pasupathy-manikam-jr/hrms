<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedJob(array $attributes = []): JobPosting
    {
        return JobPosting::factory()->create(['status' => 'Published', 'is_published' => true, 'publish_date' => today(), ...$attributes]);
    }

    public function test_guests_see_only_published_jobs_and_can_filter_them()
    {
        $php = $this->publishedJob(['title' => 'PHP Developer', 'positions' => 3, 'min_salary' => 40000]);
        $this->publishedJob(['title' => 'Sales Lead', 'positions' => 20, 'min_salary' => 120000]);
        $draft = JobPosting::factory()->create(['title' => 'Secret Role']);

        $this->get(route('career.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('career/index')->has('jobPostings.data', 2));

        $this->get(route('career.index', ['search' => 'PHP']))
            ->assertInertia(fn ($page) => $page->has('jobPostings.data', 1)->where('jobPostings.data.0.id', $php->id));
        $this->get(route('career.index', ['vacancies' => ['16-25']]))
            ->assertInertia(fn ($page) => $page->has('jobPostings.data', 1)->where('jobPostings.data.0.title', 'Sales Lead'));
        $this->get(route('career.index', ['salary' => '0-50000', 'job_types' => [$php->job_type_id]]))
            ->assertInertia(fn ($page) => $page->has('jobPostings.data', 1)->where('jobPostings.data.0.id', $php->id));

        $this->get(route('career.show', $php->job_code))
            ->assertInertia(fn ($page) => $page->component('career/show')->where('job.id', $php->id)->has('similarJobs', 1));
        $this->get(route('career.show', $draft->job_code))->assertNotFound();
    }

    public function test_applying_files_a_new_candidate_for_the_job_once()
    {
        $owner = User::factory()->create();
        $job = $this->publishedJob(['created_by' => $owner->id]);
        $source = CandidateSource::factory()->create(['name' => 'Company Website']);
        $application = ['first_name' => 'Aina', 'last_name' => 'Rahman', 'email' => 'aina@example.com', 'phone' => '012-3456789'];

        $this->post(route('career.apply', $job->job_code), ['email' => 'nope'])->assertSessionHasErrors(['first_name', 'last_name', 'email']);

        $this->post(route('career.apply', $job->job_code), $application)->assertSessionHasNoErrors();

        $candidate = Candidate::query()->sole();
        $this->assertSame(
            [$job->id, $source->id, 'New', today()->toDateString(), $owner->id],
            [$candidate->job_id, $candidate->source_id, $candidate->status, $candidate->application_date->toDateString(), $candidate->created_by],
        );

        $this->post(route('career.apply', $job->job_code), $application)->assertSessionHasErrors('email');
        $this->assertSame(1, Candidate::query()->count());

        $draft = JobPosting::factory()->create();
        $this->post(route('career.apply', $draft->job_code), $application)->assertNotFound();
    }
}
