<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingAssessment;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingProgramTest extends TestCase
{
    use RefreshDatabase;

    private function program(array $attributes = []): TrainingProgram
    {
        return TrainingProgram::create([
            'training_type_id' => TrainingType::create(['name' => 'Technical'])->id,
            'name' => 'Laravel Basics',
            'cost' => '1500.50',
            'status' => 'active',
            ...$attributes,
        ]);
    }

    public function test_list_has_status_counts_and_filters()
    {
        $this->program();
        $this->program(['name' => 'Safety Drill', 'status' => 'draft', 'is_mandatory' => true]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.training-programs.index', ['status' => 'draft']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-programs/index')
                ->has('trainingPrograms.data', 1)
                ->where('trainingPrograms.data.0.name', 'Safety Drill')
                ->where('statusCounts.all', 2)
                ->where('statusCounts.active', 1));

        $this->get(route('hr.training-programs.index', ['is_mandatory' => '0']))
            ->assertInertia(fn ($page) => $page
                ->has('trainingPrograms.data', 1)
                ->where('trainingPrograms.data.0.cost', '1500.50'));
    }

    public function test_training_programs_can_be_created_updated_and_deleted()
    {
        $type = TrainingType::create(['name' => 'Technical']);
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.training-programs.store'), ['name' => '', 'cost' => '1.234', 'status' => 'bogus'])
            ->assertSessionHasErrors(['name', 'training_type_id', 'cost', 'status']);

        $this->post(route('hr.training-programs.store'), [
            'training_type_id' => $type->id, 'name' => 'Leadership 101', 'duration' => 16,
            'cost' => '8000.25', 'capacity' => 15, 'status' => 'active', 'is_mandatory' => true,
        ])->assertSessionHasNoErrors();

        $program = TrainingProgram::where('name', 'Leadership 101')->firstOrFail();
        $this->assertSame('8000.25', $program->cost);
        $this->assertTrue($program->is_mandatory);

        $this->put(route('hr.training-programs.update', $program), [
            'training_type_id' => $type->id, 'name' => 'Leadership 102', 'cost' => '0', 'status' => 'completed',
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $program->fresh()->status);

        $this->delete(route('hr.training-programs.destroy', $program));
        $this->assertModelMissing($program);
    }

    public function test_employees_can_view_but_not_manage_programs()
    {
        $program = $this->program();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.training-programs.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('trainingPrograms.data', 1));
        $this->post(route('hr.training-programs.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.training-programs.update', $program), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.training-programs.destroy', $program))->assertForbidden();
        $this->assertModelExists($program);
    }

    public function test_show_page_has_sessions_enrolments_and_assessments()
    {
        $program = $this->program();
        $session = TrainingSession::create(['training_program_id' => $program->id, 'name' => 'Morning Batch', 'start_date' => '2026-10-01 09:00', 'end_date' => '2026-10-01 13:00', 'location_type' => 'physical', 'status' => 'scheduled']);
        $session->trainers()->attach(Employee::factory()->create());
        EmployeeTraining::create(['employee_id' => Employee::factory()->create()->id, 'training_program_id' => $program->id, 'status' => 'completed', 'assigned_date' => '2026-09-01']);
        TrainingAssessment::create(['training_program_id' => $program->id, 'name' => 'Quiz', 'type' => 'quiz', 'passing_score' => 70]);

        $this->withoutVite()->actingAs($this->userWithRole())
            ->get(route('hr.training-programs.show', $program))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-programs/show')
                ->where('trainingProgram.id', $program->id)
                ->where('trainingProgram.training_type.name', 'Technical')
                ->has('sessions', 1)
                ->has('sessions.0.trainers.0.user.name')
                ->has('enrollments', 1)
                ->has('enrollments.0.employee.user.name')
                ->has('assessments', 1));
    }

    public function test_show_page_is_scoped_to_own_programs()
    {
        $this->userWithRole();
        $user = User::factory()->create()->givePermissionTo(['manage-training-programs', 'manage-own-training-programs', 'view-training-programs']);
        $other = $this->program();
        $this->withoutVite()->actingAs($user);

        $this->get(route('hr.training-programs.show', $other))->assertNotFound();
        $this->get(route('hr.training-programs.show', $this->program(['created_by' => $user->id])))->assertOk();
    }
}
