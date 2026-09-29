<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeGoal;
use App\Models\GoalType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeGoal>
 */
class EmployeeGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'goal_type_id' => GoalType::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'target' => '10% improvement',
            'progress' => 40,
            'status' => 'in_progress',
        ];
    }
}
