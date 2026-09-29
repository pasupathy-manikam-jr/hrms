<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Leave',
            'description' => fake()->sentence(),
            'max_days_per_year' => fake()->numberBetween(1, 30),
            'is_paid' => true,
            'color' => fake()->hexColor(),
            'status' => 'active',
        ];
    }
}
