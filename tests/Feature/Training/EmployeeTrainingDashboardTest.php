<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingProgram;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTrainingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function program(string $name): TrainingProgram
    {
        return TrainingProgram::create([
            'training_type_id' => TrainingType::firstOrCreate(['name' => 'Technical'])->id,
            'name' => $name, 'cost' => '100', 'status' => 'active',
        ]);
    }

    private function assign(Employee $employee, TrainingProgram $program, string $status, array $attributes = []): EmployeeTraining
    {
        return EmployeeTraining::create([
            'employee_id' => $employee->id, 'training_program_id' => $program->id,
            'status' => $status, 'assigned_date' => '2026-09-01', ...$attributes,
        ]);
    }

    public function test_dashboard_has_statistics_program_stats_and_lists()
    {
        $laravel = $this->program('Laravel');
        $safety = $this->program('Safety');
        [$a, $b, $c] = Employee::factory()->count(3)->create();

        $this->assign($a, $laravel, 'completed', ['completion_date' => '2026-09-10']);
        $this->assign($b, $laravel, 'in_progress');
        $this->assign($c, $laravel, 'assigned', ['assigned_date' => '2026-09-20']);
        $this->assign($a, $safety, 'failed');

        $this->actingAs($this->userWithRole())
            ->get(route('hr.employee-trainings.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employee-trainings/dashboard')
                ->where('statistics', [
                    'totalTrainings' => 4, 'completedTrainings' => 1, 'inProgressTrainings' => 1,
                    'assignedTrainings' => 1, 'failedTrainings' => 1, 'completionRate' => 25,
                ])
                ->where('programStats.0', ['name' => 'Laravel', 'total' => 3, 'completed' => 1, 'completion_rate' => 33])
                ->where('programStats.1', ['name' => 'Safety', 'total' => 1, 'completed' => 0, 'completion_rate' => 0])
                ->has('recentCompletions', 1)
                ->where('recentCompletions.0.employee.user.name', $a->user->name)
                ->where('recentCompletions.0.program.name', 'Laravel')
                ->has('upcomingTrainings', 1)
                ->where('upcomingTrainings.0.assigned_date', '2026-09-20'));
    }

    public function test_employees_only_see_their_own_trainings()
    {
        $user = $this->userWithRole('employee');
        $program = $this->program('Laravel');
        $this->assign(Employee::factory()->create(['user_id' => $user->id]), $program, 'completed');
        $this->assign(Employee::factory()->create(), $program, 'assigned');

        $this->actingAs($user)
            ->get(route('hr.employee-trainings.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('statistics.totalTrainings', 1)
                ->where('statistics.completionRate', 100)
                ->has('upcomingTrainings', 0));

        $this->actingAs(User::factory()->create())
            ->get(route('hr.employee-trainings.dashboard'))
            ->assertForbidden();
    }
}
