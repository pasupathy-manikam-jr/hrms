<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\TrainingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSessionTest extends TestCase
{
    use RefreshDatabase;

    private function program(string $name = 'Laravel Basics'): TrainingProgram
    {
        return TrainingProgram::create([
            'training_type_id' => TrainingType::create(['name' => 'Technical'])->id,
            'name' => $name, 'cost' => '100', 'status' => 'active',
        ]);
    }

    private function trainingSession(TrainingProgram $program, array $attributes = []): TrainingSession
    {
        return TrainingSession::create([
            'training_program_id' => $program->id, 'name' => 'Morning Batch',
            'start_date' => '2026-10-01 09:00', 'end_date' => '2026-10-01 13:00',
            'location_type' => 'physical', 'status' => 'scheduled', ...$attributes,
        ]);
    }

    public function test_list_has_status_counts_and_date_filters()
    {
        $program = $this->program();
        $this->trainingSession($program);
        $this->trainingSession($program, ['name' => 'Evening Online', 'start_date' => '2026-11-05 18:00', 'end_date' => '2026-11-05 20:00', 'status' => 'completed']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.training-sessions.index', ['date_from' => '2026-11-01']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-sessions/index')
                ->has('trainingSessions.data', 1)
                ->where('trainingSessions.data.0.name', 'Evening Online')
                ->where('trainingSessions.data.0.start_date', '2026-11-05T18:00')
                ->where('statusCounts.completed', 1));

        $this->get(route('hr.training-sessions.index', ['status' => 'scheduled']))
            ->assertInertia(fn ($page) => $page->has('trainingSessions.data', 1)->where('statusCounts.all', 2));
    }

    public function test_sessions_can_be_created_updated_and_deleted()
    {
        $program = $this->program();
        $trainer = Employee::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.training-sessions.store'), [
            'training_program_id' => $program->id, 'name' => 'Online', 'start_date' => '2026-10-01T10:00',
            'end_date' => '2026-10-01T09:00', 'location_type' => 'virtual', 'status' => 'scheduled',
        ])->assertSessionHasErrors(['end_date', 'meeting_link']);

        $this->post(route('hr.training-sessions.store'), [
            'training_program_id' => $program->id, 'name' => 'Online', 'start_date' => '2026-10-01T10:00',
            'end_date' => '2026-10-01T12:00', 'location_type' => 'virtual', 'meeting_link' => 'https://meet.example.com/x',
            'status' => 'scheduled', 'trainer_ids' => [$trainer->id],
        ])->assertSessionHasNoErrors();

        $session = TrainingSession::where('name', 'Online')->firstOrFail();
        $this->assertSame([$trainer->id], $session->trainers()->pluck('employees.id')->all());

        $this->put(route('hr.training-sessions.update', $session), [
            'training_program_id' => $program->id, 'name' => 'Room 1', 'start_date' => '2026-10-01T10:00',
            'end_date' => '2026-10-01T12:00', 'location_type' => 'physical', 'status' => 'completed',
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $session->fresh()->status);
        $this->assertCount(0, $session->trainers()->get());

        $this->delete(route('hr.training-sessions.destroy', $session));
        $this->assertModelMissing($session);
    }

    public function test_employees_only_see_sessions_they_train_or_are_enrolled_in()
    {
        $user = $this->userWithRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $enrolled = $this->program('Enrolled');
        EmployeeTraining::create(['employee_id' => $employee->id, 'training_program_id' => $enrolled->id, 'status' => 'assigned', 'assigned_date' => '2026-09-01']);
        $this->trainingSession($enrolled, ['name' => 'Enrolled Session']);
        $this->trainingSession($this->program('Trained'), ['name' => 'Trainer Session'])->trainers()->attach($employee);
        $other = $this->trainingSession($this->program('Other'), ['name' => 'Other Session']);

        $this->actingAs($user)
            ->get(route('hr.training-sessions.index', ['sort_field' => 'name', 'sort_direction' => 'asc']))
            ->assertInertia(fn ($page) => $page
                ->has('trainingSessions.data', 2)
                ->where('trainingSessions.data.0.name', 'Enrolled Session')
                ->where('trainingSessions.data.1.name', 'Trainer Session'));

        $this->post(route('hr.training-sessions.store'), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.training-sessions.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);
    }

    public function test_show_page_has_trainers_and_participants_and_is_scoped()
    {
        $program = $this->program();
        $session = $this->trainingSession($program);
        $session->trainers()->attach(Employee::factory()->create());
        EmployeeTraining::create(['employee_id' => Employee::factory()->create()->id, 'training_program_id' => $program->id, 'training_session_id' => $session->id, 'status' => 'assigned', 'assigned_date' => '2026-09-01']);
        $this->withoutVite();

        $this->actingAs($this->userWithRole())
            ->get(route('hr.training-sessions.show', $session))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-sessions/show')
                ->where('trainingSession.id', $session->id)
                ->where('trainingSession.program.name', 'Laravel Basics')
                ->has('trainingSession.trainers.0.user.name')
                ->has('participants', 1)
                ->has('participants.0.employee.user.name'));

        // Employees only reach sessions they train or are enrolled in.
        $this->actingAs($this->userWithRole('employee'))->get(route('hr.training-sessions.show', $session))->assertNotFound();
    }
}
