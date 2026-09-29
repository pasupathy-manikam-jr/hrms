<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\InterviewRound;
use App\Models\InterviewType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(Candidate $candidate, User $interviewer, array $overrides = []): array
    {
        return $overrides + [
            'candidate_id' => $candidate->id,
            'round_id' => InterviewRound::factory()->create(['job_id' => $candidate->job_id])->id,
            'interview_type_id' => InterviewType::factory()->create()->id,
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '10:30',
            'duration' => 45,
            'location' => 'Room A',
            'interviewers' => [$interviewer->id],
            'status' => 'Scheduled',
        ];
    }

    public function test_list_can_be_searched_by_candidate_and_filtered_by_status()
    {
        Interview::factory()->count(2)->create();
        $target = Interview::factory()->create([
            'candidate_id' => Candidate::factory()->create(['first_name' => 'Zelda']),
            'status' => 'Completed',
        ]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.interviews.index', ['search' => 'Zelda']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/interviews/index')
                ->has('interviews.data', 1)
                ->where('interviews.data.0.id', $target->id)
                ->where('statusCounts.all', 3)
                ->where('statusCounts.Completed', 1));

        $this->get(route('hr.recruitment.interviews.index', ['status' => 'Completed']))
            ->assertInertia(fn ($page) => $page->has('interviews.data', 1)->where('filters.status', 'Completed'));
    }

    public function test_week_strip_day_filter_and_next_upcoming_day()
    {
        $this->travelTo('2030-03-06 09:00'); // a Wednesday
        $late = Interview::factory()->create(['scheduled_date' => '2030-03-07', 'scheduled_time' => '15:00', 'status' => 'Scheduled', 'feedback_submitted' => false]);
        $early = Interview::factory()->create(['scheduled_date' => '2030-03-07', 'scheduled_time' => '09:00', 'status' => 'Scheduled', 'feedback_submitted' => false]);
        Interview::factory()->create(['scheduled_date' => '2030-03-04', 'status' => 'Completed', 'feedback_submitted' => true]);
        Interview::factory()->create(['scheduled_date' => '2030-03-12', 'status' => 'Scheduled', 'feedback_submitted' => false]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.interviews.index'))
            ->assertInertia(fn ($page) => $page
                ->where('weekStart', '2030-03-04')
                ->where('weekCounts', ['2030-03-04' => 1, '2030-03-07' => 2])
                ->where('upcoming.date', '2030-03-07')
                ->where('upcoming.interviews', fn ($interviews) => collect($interviews)->pluck('id')->all() === [$early->id, $late->id])
                ->where('pendingFeedback', 3)
                ->has('interviews.data', 4));

        // Picking a day lists only that day, earliest first.
        $this->get(route('hr.recruitment.interviews.index', ['selected_date' => '2030-03-07']))
            ->assertInertia(fn ($page) => $page->has('interviews.data', 2)->where('interviews.data.0.id', $early->id));

        $this->get(route('hr.recruitment.interviews.index', ['selected_date' => 'someday']))->assertSessionHasErrors('selected_date');
    }

    public function test_scheduling_saves_interviewers_and_moves_an_early_candidate_to_interview()
    {
        $user = $this->userWithRole();
        $candidate = Candidate::factory()->create(['status' => 'Screening']);
        $this->actingAs($user);

        $this->post(route('hr.recruitment.interviews.store'), ['candidate_id' => $candidate->id])
            ->assertSessionHasErrors(['round_id', 'interview_type_id', 'scheduled_date', 'scheduled_time', 'interviewers', 'status']);

        $this->post(route('hr.recruitment.interviews.store'), $this->payload($candidate, $user))->assertSessionHasNoErrors();

        $interview = Interview::with('interviewers')->sole();
        $this->assertSame($candidate->job_id, $interview->job_id);
        $this->assertSame([$user->id], $interview->interviewers->modelKeys());
        $this->assertSame('Interview', $candidate->fresh()->status);
    }

    public function test_later_stage_candidates_keep_their_status_and_rounds_must_belong_to_the_job()
    {
        $user = $this->userWithRole();
        $candidate = Candidate::factory()->create(['status' => 'Offer']);
        $this->actingAs($user);

        $this->post(route('hr.recruitment.interviews.store'), $this->payload($candidate, $user, [
            'round_id' => InterviewRound::factory()->create()->id,
        ]))->assertSessionHasErrors('round_id');

        $this->post(route('hr.recruitment.interviews.store'), $this->payload($candidate, $user))->assertSessionHasNoErrors();
        $this->assertSame('Offer', $candidate->fresh()->status);
    }

    public function test_interviews_can_be_updated_status_changed_and_deleted()
    {
        $user = $this->userWithRole();
        $interview = Interview::factory()->create();
        $this->actingAs($user);

        $this->put(route('hr.recruitment.interviews.update', $interview), $this->payload($interview->candidate, $user, ['duration' => 90]))
            ->assertSessionHasNoErrors();
        $this->assertSame(90, $interview->fresh()->duration);

        $this->put(route('hr.recruitment.interviews.update-status', $interview), ['status' => 'Bogus'])->assertSessionHasErrors('status');
        $this->put(route('hr.recruitment.interviews.update-status', $interview), ['status' => 'No-show'])->assertSessionHasNoErrors();
        $this->assertSame('No-show', $interview->fresh()->status);

        $this->delete(route('hr.recruitment.interviews.destroy', $interview));
        $this->assertModelMissing($interview);
    }

    public function test_interviewers_only_see_their_own_interviews_and_cannot_change_them()
    {
        $employee = $this->userWithRole('employee');
        $mine = Interview::factory()->create();
        $mine->interviewers()->attach($employee);
        Interview::factory()->create();

        $this->actingAs($employee)
            ->get(route('hr.recruitment.interviews.index'))
            ->assertInertia(fn ($page) => $page->has('interviews.data', 1)->where('interviews.data.0.id', $mine->id));

        $this->post(route('hr.recruitment.interviews.store'), [])->assertForbidden();
        $this->put(route('hr.recruitment.interviews.update-status', $mine), ['status' => 'Completed'])->assertForbidden();
        $this->delete(route('hr.recruitment.interviews.destroy', $mine))->assertForbidden();
    }

    public function test_show_page_has_the_panel_and_the_feedback_the_user_may_read()
    {
        $employee = $this->userWithRole('employee');
        $colleague = User::factory()->create();
        $interview = Interview::factory()->create();
        $interview->interviewers()->attach([$employee->id, $colleague->id]);
        InterviewFeedback::factory()->create(['interview_id' => $interview->id, 'interviewer_id' => $employee->id]);
        InterviewFeedback::factory()->create(['interview_id' => $interview->id, 'interviewer_id' => $colleague->id]);
        $other = Interview::factory()->create();
        $this->withoutVite();

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.interviews.show', $interview))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/interviews/show')
                ->where('interview.id', $interview->id)
                ->has('interview.interviewers', 2)
                ->has('feedback', 2)
                ->has('feedback.0.interviewer.name'));

        // Interviewers only read their own feedback, and only for interviews they sit on.
        $this->actingAs($employee)
            ->get(route('hr.recruitment.interviews.show', $interview))
            ->assertInertia(fn ($page) => $page->has('feedback', 1)->where('feedback.0.interviewer_id', $employee->id));
        $this->get(route('hr.recruitment.interviews.show', $other))->assertNotFound();
    }
}
