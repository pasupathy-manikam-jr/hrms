<?php

namespace Database\Factories;

use App\Models\ReviewCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewCycle>
 */
class ReviewCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'frequency' => 'Quarterly',
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
