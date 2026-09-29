<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Warning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warning>
 */
class WarningFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'warning_type' => fake()->randomElement(Warning::TYPES),
            'subject' => fake()->sentence(3),
            'severity' => fake()->randomElement(Warning::SEVERITIES),
            'warning_date' => fake()->date(),
            'description' => fake()->sentence(),
            'status' => 'draft',
        ];
    }
}
