<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingAssessment;
use App\Models\TrainingProgram;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function program(string $name = 'Laravel Basics'): TrainingProgram
    {
        return TrainingProgram::create([
            'training_type_id' => TrainingType::create(['name' => 'Technical'])->id,
            'name' => $name, 'cost' => '100', 'status' => 'active',
        ]);
    }

    public function test_list_has_type_counts_and_results()
    {
        $program = $this->program();
        $quiz = TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Policy Quiz', 'type' => 'quiz', 'passing_score' => 80]);
        TrainingAssessment::create(['training_program_id' => $this->program('Other')->id, 'name' => 'Demo Day', 'type' => 'presentation', 'passing_score' => 70]);
        $training = EmployeeTraining::create(['employee_id' => Employee::factory()->create()->id, 'training_program_id' => $program->id, 'status' => 'completed', 'assigned_date' => '2026-09-01']);
        $training->results()->create(['training_assessment_id' => $quiz->id, 'score' => 90, 'is_passed' => true, 'assessment_date' => '2026-09-10']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.training-assessments.index', ['type' => 'quiz']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-assessments/index')
                ->has('trainingAssessments.data', 1)
                ->where('trainingAssessments.data.0.results_count', 1)
                ->where('trainingAssessments.data.0.results.0.score', '90.00')
                ->where('typeCounts', ['all' => 2, 'quiz' => 1, 'practical' => 0, 'presentation' => 1]));

        $this->get(route('hr.training-assessments.index', ['training_program_id' => $program->id]))
            ->assertInertia(fn ($page) => $page->has('trainingAssessments.data', 1));
    }

    public function test_assessments_can_be_created_updated_and_deleted()
    {
        $program = $this->program();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.training-assessments.store'), ['type' => 'exam', 'passing_score' => 120])
            ->assertSessionHasErrors(['training_program_id', 'name', 'type', 'passing_score']);

        $this->post(route('hr.training-assessments.store'), ['training_program_id' => $program->id, 'name' => 'Coding Test', 'type' => 'practical', 'passing_score' => 75])
            ->assertSessionHasNoErrors();

        $assessment = TrainingAssessment::where('name', 'Coding Test')->firstOrFail();

        $this->put(route('hr.training-assessments.update', $assessment), ['training_program_id' => $program->id, 'name' => 'Coding Test', 'type' => 'quiz', 'passing_score' => 60.5])
            ->assertSessionHasNoErrors();
        $this->assertSame('60.50', $assessment->fresh()->passing_score);

        $this->delete(route('hr.training-assessments.destroy', $assessment));
        $this->assertModelMissing($assessment);
    }

    public function test_hr_and_employees_cannot_manage_assessments()
    {
        $assessment = TrainingAssessment::create(['training_program_id' => $this->program()->id, 'name' => 'Quiz', 'type' => 'quiz', 'passing_score' => 80]);

        foreach (['hr', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->get(route('hr.training-assessments.index'))->assertForbidden();
            $this->delete(route('hr.training-assessments.destroy', $assessment))->assertForbidden();
        }

        $this->assertModelExists($assessment);
    }

    public function test_show_page_has_results_per_employee_and_statistics()
    {
        $program = $this->program();
        $quiz = TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Policy Quiz', 'type' => 'quiz', 'passing_score' => 80]);
        foreach ([90, 60] as $score) {
            $training = EmployeeTraining::create(['employee_id' => Employee::factory()->create()->id, 'training_program_id' => $program->id, 'status' => 'completed', 'assigned_date' => '2026-09-01']);
            $training->results()->create(['training_assessment_id' => $quiz->id, 'score' => $score, 'is_passed' => $score >= 80, 'assessment_date' => '2026-09-10']);
        }

        $this->withoutVite()->actingAs($this->userWithRole())
            ->get(route('hr.training-assessments.show', $quiz))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-assessments/show')
                ->where('trainingAssessment.program.name', 'Laravel Basics')
                ->has('results', 2)
                ->has('results.0.employee_training.employee.user.name')
                ->where('statistics', ['total' => 2, 'passed' => 1, 'averageScore' => 75]));
    }

    public function test_show_page_is_scoped_to_own_assessments()
    {
        $this->userWithRole();
        $user = User::factory()->create()->givePermissionTo(['manage-training-assessments', 'view-training-assessments']);
        $program = $this->program();
        $other = TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Theirs', 'type' => 'quiz', 'passing_score' => 70]);
        $this->withoutVite()->actingAs($user);

        $this->get(route('hr.training-assessments.show', $other))->assertNotFound();
        $this->get(route('hr.training-assessments.show', TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Mine', 'type' => 'quiz', 'passing_score' => 70, 'created_by' => $user->id])))->assertOk();
    }
}
