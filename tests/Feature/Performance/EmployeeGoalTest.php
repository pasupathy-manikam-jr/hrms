<?php

namespace Tests\Feature\Performance;

use App\Models\Employee;
use App\Models\EmployeeGoal;
use App\Models\GoalType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        EmployeeGoal::factory()->count(2)->create();
        $goal = EmployeeGoal::factory()->create(['title' => 'Zeta Launch', 'status' => 'completed', 'progress' => 100]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.performance.employee-goals.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/performance/employee-goals/index')
                ->has('goals.data', 1)
                ->where('goals.data.0.employee.user.name', $goal->employee->user->name)
                ->where('goals.data.0.goal_type.name', $goal->goalType->name)
                ->has('employees', 3)
                ->where('statusCounts.all', 1));

        $this->get(route('hr.performance.employee-goals.index', ['status' => 'in_progress']))
            ->assertInertia(fn ($page) => $page->has('goals.data', 2)->where('statusCounts.completed', 1));

        $this->get(route('hr.performance.employee-goals.index', ['employee_id' => $goal->employee_id]))
            ->assertInertia(fn ($page) => $page->has('goals.data', 1));
    }

    public function test_goals_can_be_created_updated_and_deleted()
    {
        $employee = Employee::factory()->create();
        $type = GoalType::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $payload = [
            'employee_id' => $employee->id, 'goal_type_id' => $type->id, 'title' => 'Ship v2',
            'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'target' => 'On time', 'progress' => 20, 'status' => 'in_progress',
        ];

        $this->post(route('hr.performance.employee-goals.store'), [...$payload, 'progress' => 150, 'end_date' => '2025-12-01', 'status' => 'bogus'])
            ->assertSessionHasErrors(['progress', 'end_date', 'status']);
        $this->post(route('hr.performance.employee-goals.store'), $payload)->assertSessionHasNoErrors();

        $goal = EmployeeGoal::where('title', 'Ship v2')->firstOrFail();

        $this->put(route('hr.performance.employee-goals.update', $goal), [...$payload, 'progress' => 100, 'status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame(100, $goal->fresh()->progress);
        $this->assertSame('completed', $goal->fresh()->status);

        $this->delete(route('hr.performance.employee-goals.destroy', $goal));
        $this->assertModelMissing($goal);
    }

    public function test_progress_update_moves_the_status()
    {
        $goal = EmployeeGoal::factory()->create(['status' => 'not_started', 'progress' => 0]);
        $this->actingAs($this->userWithRole('hr'));

        $this->put(route('hr.performance.employee-goals.progress', $goal), ['progress' => 120])->assertSessionHasErrors('progress');

        $this->put(route('hr.performance.employee-goals.progress', $goal), ['progress' => 40])->assertSessionHasNoErrors();
        $this->assertSame([40, 'in_progress'], [$goal->fresh()->progress, $goal->fresh()->status]);

        $this->put(route('hr.performance.employee-goals.progress', $goal), ['progress' => 100])->assertSessionHasNoErrors();
        $this->assertSame('completed', $goal->fresh()->status);
    }

    public function test_employees_only_see_their_own_goals_and_cannot_change_them()
    {
        $user = $this->userWithRole('employee');
        $mine = EmployeeGoal::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        $other = EmployeeGoal::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.performance.employee-goals.index'))
            ->assertInertia(fn ($page) => $page
                ->has('goals.data', 1)
                ->where('goals.data.0.id', $mine->id)
                ->where('employees', []));

        // The id filter cannot widen the scope.
        $this->get(route('hr.performance.employee-goals.index', ['employee_id' => $other->employee_id]))
            ->assertInertia(fn ($page) => $page->has('goals.data', 0));

        $this->post(route('hr.performance.employee-goals.store'), [])->assertForbidden();
        $this->put(route('hr.performance.employee-goals.update', $mine), [])->assertForbidden();
        $this->put(route('hr.performance.employee-goals.progress', $mine), ['progress' => 50])->assertForbidden();
        $this->delete(route('hr.performance.employee-goals.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);
    }

    public function test_manage_own_editors_cannot_touch_other_employees_goals()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-employee-goals', 'manage-own-employee-goals', 'edit-employee-goals', 'delete-employee-goals']);
        $other = EmployeeGoal::factory()->create();

        $this->actingAs($user)->delete(route('hr.performance.employee-goals.destroy', $other))->assertNotFound();
        $this->assertModelExists($other);
    }
}
