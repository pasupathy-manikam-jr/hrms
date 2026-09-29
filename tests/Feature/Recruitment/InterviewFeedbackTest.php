<?php

namespace Tests\Feature\Recruitment;

use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function interviewWith(User $interviewer): Interview
    {
        $interview = Interview::factory()->create();
        $interview->interviewers()->attach($interviewer);

        return $interview;
    }

    public function test_hr_records_feedback_for_a_panel_member_and_the_interview_is_flagged()
    {
        $this->actingAs($this->userWithRole());
        $panelist = User::factory()->create();
        $interview = $this->interviewWith($panelist);
        $payload = ['interview_id' => $interview->id, 'interviewer_id' => $panelist->id, 'overall_rating' => 4, 'technical_rating' => 5, 'recommendation' => 'Hire'];

        $this->post(route('hr.recruitment.interview-feedback.store'), ['interview_id' => $interview->id, 'interviewer_id' => User::factory()->create()->id, 'overall_rating' => 9, 'recommendation' => 'Yes'])
            ->assertSessionHasErrors(['interviewer_id', 'overall_rating', 'recommendation']);
        $this->post(route('hr.recruitment.interview-feedback.store'), $payload)->assertSessionHasNoErrors();

        $feedback = InterviewFeedback::sole();
        $this->assertSame($panelist->id, $feedback->interviewer_id);
        $this->assertTrue($interview->fresh()->feedback_submitted);

        $this->get(route('hr.recruitment.interview-feedback.index', ['recommendation' => 'Hire']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/interview-feedback/index')
                ->has('interviewFeedback.data', 1)
                ->where('interviewFeedback.data.0.interviewer.id', $panelist->id));

        $this->put(route('hr.recruitment.interview-feedback.update', $feedback), ['recommendation' => 'Strong Hire'] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('Strong Hire', $feedback->fresh()->recommendation);

        $this->delete(route('hr.recruitment.interview-feedback.destroy', $feedback));
        $this->assertModelMissing($feedback);
        $this->assertFalse($interview->fresh()->feedback_submitted);
    }

    public function test_interviewers_see_and_write_only_their_own_feedback()
    {
        $employee = $this->userWithRole('employee');
        $mine = $this->interviewWith($employee);
        $notMine = Interview::factory()->create();
        $own = InterviewFeedback::factory()->create(['interview_id' => $mine->id, 'interviewer_id' => $employee->id]);
        $other = InterviewFeedback::factory()->create(['interview_id' => $mine->id, 'interviewer_id' => User::factory()->create()->id]);
        $this->actingAs($employee);

        $this->get(route('hr.recruitment.interview-feedback.index'))
            ->assertInertia(fn ($page) => $page
                ->has('interviewFeedback.data', 1)
                ->where('interviewFeedback.data.0.id', $own->id)
                ->has('interviews', 1));

        $this->post(route('hr.recruitment.interview-feedback.store'), ['interview_id' => $notMine->id, 'overall_rating' => 3, 'recommendation' => 'Maybe'])
            ->assertSessionHasErrors('interview_id');

        // Any interviewer_id sent is ignored: the feedback is always the interviewer's own.
        $this->post(route('hr.recruitment.interview-feedback.store'), ['interview_id' => $mine->id, 'interviewer_id' => $other->interviewer_id, 'overall_rating' => 3, 'recommendation' => 'Maybe'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, InterviewFeedback::where('interviewer_id', $employee->id)->count());

        $this->put(route('hr.recruitment.interview-feedback.update', $other), ['interview_id' => $mine->id, 'overall_rating' => 1, 'recommendation' => 'Reject'])->assertForbidden();
        $this->delete(route('hr.recruitment.interview-feedback.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);
    }
}
