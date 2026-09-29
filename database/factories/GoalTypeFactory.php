<?php

namespace Database\Factories;

use App\Models\GoalType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalType>
 */
class GoalTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
