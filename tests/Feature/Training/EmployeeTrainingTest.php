<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingAssessment;
use App\Models\TrainingProgram;
use App\Models\TrainingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTrainingTest extends TestCase
{
    use RefreshDatabase;

    private function program(array $attributes = []): TrainingProgram
    {
        return TrainingProgram::create([
            'training_type_id' => TrainingType::create(['name' => 'Technical'])->id,
            'name' => 'Laravel Basics', 'cost' => '100', 'status' => 'active', ...$attributes,
        ]);
    }

    private function assign(Employee $employee, TrainingProgram $program, string $status = 'assigned'): EmployeeTraining
    {
        return EmployeeTraining::create([
            'employee_id' => $employee->id, 'training_program_id' => $program->id,
            'status' => $status, 'assigned_date' => '2026-09-01',
        ]);
    }

    public function test_list_has_status_counts_search_and_filters()
    {
        $program = $this->program();
        $alice = Employee::factory()->create();
        $alice->user->update(['name' => 'Alice Moss']);
        $this->assign($alice, $program, 'completed');
        $this->assign(Employee::factory()->create(), $program);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.employee-trainings.index', ['search' => 'Alice']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employee-trainings/index')
                ->has('employeeTrainings.data', 1)
                ->where('employeeTrainings.data.0.employee.user.name', 'Alice Moss')
                ->where('statusCounts.completed', 1)
                ->has('employees', 2));

        $this->get(route('hr.employee-trainings.index', ['status' => 'assigned']))
            ->assertInertia(fn ($page) => $page->has('employeeTrainings.data', 1)->where('statusCounts.all', 2));
    }

    public function test_trainings_can_be_assigned_updated_and_deleted()
    {
        $program = $this->program();
        $employee = Employee::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.employee-trainings.store'), ['status' => 'bogus', 'score' => 150])
            ->assertSessionHasErrors(['employee_id', 'training_program_id', 'status', 'assigned_date', 'score']);

        $payload = ['employee_id' => $employee->id, 'training_program_id' => $program->id, 'status' => 'assigned', 'assigned_date' => '2026-09-01'];
        $this->post(route('hr.employee-trainings.store'), $payload)->assertSessionHasNoErrors();
        // One assignment per employee and program.
        $this->post(route('hr.employee-trainings.store'), $payload)->assertSessionHasErrors('training_program_id');

        $training = EmployeeTraining::firstOrFail();

        $this->put(route('hr.employee-trainings.update', $training), [...$payload, 'status' => 'completed', 'score' => 88.5, 'certification' => true])
            ->assertSessionHasNoErrors();
        $training->refresh();
        $this->assertSame('completed', $training->status);
        $this->assertSame('88.50', $training->score);
        $this->assertTrue($training->certification);
        $this->assertSame(now()->toDateString(), $training->completion_date->toDateString());

        // Back in progress: completion date and certificate are cleared.
        $this->put(route('hr.employee-trainings.update', $training), [...$payload, 'status' => 'in_progress', 'certification' => true]);
        $this->assertNull($training->fresh()->completion_date);
        $this->assertFalse($training->fresh()->certification);

        $this->delete(route('hr.employee-trainings.destroy', $training));
        $this->assertModelMissing($training);
    }

    public function test_bulk_assign_skips_employees_already_assigned()
    {
        $program = $this->program();
        [$first, $second] = Employee::factory()->count(2)->create();
        $this->assign($first, $program);
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.employee-trainings.bulk-assign'), [
            'training_program_id' => $program->id, 'employee_ids' => [$first->id, $second->id], 'assigned_date' => '2026-09-10',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, EmployeeTraining::count());
        $this->assertSame('2026-09-01', EmployeeTraining::where('employee_id', $first->id)->first()->assigned_date->toDateString());
    }

    public function test_assessment_results_are_recorded_with_pass_mark()
    {
        $program = $this->program();
        $training = $this->assign(Employee::factory()->create(), $program, 'in_progress');
        $quiz = TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Quiz', 'type' => 'quiz', 'passing_score' => 75]);
        $otherProgramQuiz = TrainingAssessment::create(['training_program_id' => $this->program(['name' => 'Other'])->id, 'name' => 'Other Quiz', 'type' => 'quiz', 'passing_score' => 50]);
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.employee-trainings.record-assessment', $training), ['training_assessment_id' => $otherProgramQuiz->id, 'score' => 80, 'assessment_date' => '2026-09-20'])
            ->assertSessionHasErrors('training_assessment_id');

        $this->post(route('hr.employee-trainings.record-assessment', $training), ['training_assessment_id' => $quiz->id, 'score' => 70, 'assessment_date' => '2026-09-20'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($training->results()->sole()->is_passed);

        // Re-recording updates the same result.
        $this->post(route('hr.employee-trainings.record-assessment', $training), ['training_assessment_id' => $quiz->id, 'score' => 75, 'assessment_date' => '2026-09-21']);
        $result = $training->results()->sole();
        $this->assertTrue($result->is_passed);
        $this->assertSame('75.00', $result->score);
    }

    public function test_employees_see_only_their_own_trainings_and_can_self_enroll()
    {
        $user = $this->userWithRole('employee');
        $own = Employee::factory()->create(['user_id' => $user->id]);
        $other = Employee::factory()->create();
        $program = $this->program();
        $ownTraining = $this->assign($own, $program);
        $otherTraining = $this->assign($other, $program);
        $quiz = TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Quiz', 'type' => 'quiz', 'passing_score' => 75]);
        $this->actingAs($user);

        $this->get(route('hr.employee-trainings.index'))
            ->assertInertia(fn ($page) => $page
                ->has('employeeTrainings.data', 1)
                ->where('employeeTrainings.data.0.id', $ownTraining->id)
                ->where('statusCounts.all', 1)
                ->where('employees', []));

        $this->post(route('hr.employee-trainings.store'), [])->assertForbidden();
        $this->delete(route('hr.employee-trainings.destroy', $otherTraining))->assertForbidden();

        // Recording results: never someone else's, never your own.
        $result = ['training_assessment_id' => $quiz->id, 'score' => 100, 'assessment_date' => '2026-09-20'];
        $this->post(route('hr.employee-trainings.record-assessment', $otherTraining), $result)->assertNotFound();
        $this->post(route('hr.employee-trainings.record-assessment', $ownTraining), $result)->assertForbidden();
        $this->assertSame(0, $quiz->results()->count());

        // Self-enrollment: only themselves, only into self-enrollment programs.
        $closed = $this->program(['name' => 'Closed']);
        $open = $this->program(['name' => 'Open', 'is_self_enrollment' => true]);
        $this->post(route('hr.employee-trainings.bulk-assign'), ['training_program_id' => $closed->id, 'assigned_date' => '2026-09-20'])
            ->assertSessionHasErrors('training_program_id');
        $this->post(route('hr.employee-trainings.bulk-assign'), ['training_program_id' => $open->id, 'employee_ids' => [$other->id], 'assigned_date' => '2026-09-20'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(EmployeeTraining::where('training_program_id', $open->id)->where('employee_id', $own->id)->exists());
        $this->assertFalse(EmployeeTraining::where('training_program_id', $open->id)->where('employee_id', $other->id)->exists());
    }

    public function test_show_page_has_the_assignment_and_results_and_is_scoped()
    {
        $program = $this->program();
        $training = $this->assign(Employee::factory()->create(), $program, 'completed');
        $assessment = TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Quiz', 'type' => 'quiz', 'passing_score' => 70]);
        $training->results()->create(['training_assessment_id' => $assessment->id, 'score' => 85, 'is_passed' => true, 'assessment_date' => '2026-09-10']);
        $this->withoutVite();

        $this->actingAs($this->userWithRole())
            ->get(route('hr.employee-trainings.show', $training))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employee-trainings/show')
                ->where('employeeTraining.id', $training->id)
                ->has('employeeTraining.employee.user.name')
                ->where('employeeTraining.program.training_type.name', 'Technical')
                ->where('employeeTraining.results.0.assessment.name', 'Quiz')
                ->where('employeeTraining.results.0.is_passed', true));

        // Employees only see their own trainings.
        $user = $this->userWithRole('employee');
        $mine = $this->assign(Employee::factory()->create(['user_id' => $user->id]), $program);
        $this->actingAs($user);
        $this->get(route('hr.employee-trainings.show', $mine))->assertOk();
        $this->get(route('hr.employee-trainings.show', $training))->assertNotFound();
    }
}
