<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'basic_salary' => fake()->numberBetween(15, 80) * 1000,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
