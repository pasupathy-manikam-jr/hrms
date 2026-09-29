<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\CandidateAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function assessment(array $attributes = []): CandidateAssessment
    {
        return CandidateAssessment::create($attributes + [
            'candidate_id' => Candidate::factory()->create()->id,
            'assessment_name' => 'Aptitude Test',
            'assessment_date' => '2026-01-18',
            'score' => 78,
            'max_score' => 100,
        ]);
    }

    public function test_result_is_derived_from_the_score()
    {
        $this->assertSame('Pass', $this->assessment(['score' => 72])->pass_fail_status);
        $this->assertSame('Fail', $this->assessment(['score' => 65])->pass_fail_status);
        $this->assertSame('Pending', $this->assessment(['score' => null])->pass_fail_status);
        $this->assertSame('Pass', $this->assessment(['score' => 35, 'max_score' => 50])->pass_fail_status);
    }

    public function test_list_can_be_filtered_by_result_and_candidate()
    {
        $pass = $this->assessment(['assessment_name' => 'Coding Challenge', 'score' => 95]);
        $this->assessment(['score' => 40]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.candidate-assessments.index', ['status' => 'Pass']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/candidate-assessments/index')
                ->has('assessments.data', 1)
                ->where('assessments.data.0.assessment_name', 'Coding Challenge')
                ->where('statusCounts', ['all' => 2, 'Pass' => 1, 'Fail' => 1, 'Pending' => 0])
                ->has('candidates', 2));

        $this->get(route('hr.recruitment.candidate-assessments.index', ['candidate_id' => $pass->candidate_id]))
            ->assertInertia(fn ($page) => $page->has('assessments.data', 1)->where('assessments.data.0.id', $pass->id));
    }

    public function test_assessments_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $candidate = Candidate::factory()->create();
        $this->actingAs($user);

        $this->post(route('hr.recruitment.candidate-assessments.store'), ['candidate_id' => 999, 'assessment_name' => '', 'assessment_date' => '', 'max_score' => 100, 'score' => 120])
            ->assertSessionHasErrors(['candidate_id', 'assessment_name', 'assessment_date', 'score']);

        $this->post(route('hr.recruitment.candidate-assessments.store'), [
            'candidate_id' => $candidate->id, 'assessment_name' => 'Case Study', 'assessment_date' => '2026-01-16',
            'score' => 50, 'max_score' => 100, 'pass_fail_status' => 'Pass', 'conducted_by' => $user->id,
        ])->assertSessionHasNoErrors();

        $record = CandidateAssessment::where('assessment_name', 'Case Study')->firstOrFail();
        $this->assertSame($user->id, $record->created_by);
        $this->assertSame('Fail', $record->pass_fail_status, 'The client cannot set the result.');

        $this->put(route('hr.recruitment.candidate-assessments.update', $record), [
            'candidate_id' => $candidate->id, 'assessment_name' => 'Case Study', 'assessment_date' => '2026-01-16', 'score' => 80, 'max_score' => 100,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Pass', $record->fresh()->pass_fail_status);

        $this->delete(route('hr.recruitment.candidate-assessments.destroy', $record));
        $this->assertModelMissing($record);
    }

    public function test_employees_only_manage_their_own_assessments()
    {
        $employee = $this->userWithRole('employee');
        $mine = $this->assessment(['created_by' => $employee->id]);
        $other = $this->assessment();

        $this->actingAs($employee)
            ->get(route('hr.recruitment.candidate-assessments.index'))
            ->assertInertia(fn ($page) => $page->has('assessments.data', 1)->where('assessments.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.candidate-assessments.update', $other), [
            'candidate_id' => $other->candidate_id, 'assessment_name' => 'X', 'assessment_date' => '2026-01-16', 'max_score' => 100,
        ])->assertForbidden();
        $this->delete(route('hr.recruitment.candidate-assessments.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);

        $this->delete(route('hr.recruitment.candidate-assessments.destroy', $mine))->assertRedirect();
        $this->assertModelMissing($mine);
    }

    public function test_users_without_the_permission_are_denied()
    {
        $user = $this->userWithRole('employee');
        $user->syncRoles([]);

        $this->actingAs($user)->get(route('hr.recruitment.candidate-assessments.index'))->assertForbidden();
        $this->post(route('hr.recruitment.candidate-assessments.store'), [])->assertForbidden();
    }
}
