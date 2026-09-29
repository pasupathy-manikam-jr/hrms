<?php

namespace Database\Factories;

use App\Models\InterviewType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewType>
 */
class InterviewTypeFactory extends Factory
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
