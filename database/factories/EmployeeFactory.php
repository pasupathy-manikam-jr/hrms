<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_id' => fake()->unique()->bothify('EMP######'),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->date(max: '-20 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'designation_id' => Designation::factory(),
            'department_id' => fn (array $attributes) => Designation::query()->whereKey($attributes['designation_id'])->value('department_id'),
            'branch_id' => fn (array $attributes) => Department::query()->whereKey($attributes['department_id'])->value('branch_id'),
            // Fixed range before the 2025-2026 payroll periods the tests run, so those runs always include the employee.
            'date_of_joining' => fake()->dateTimeBetween('2000-01-01', '2024-12-31')->format('Y-m-d'),
            'employment_type' => 'Full-time',
            'employee_status' => 'active',
        ];
    }
}
