<?php

namespace Database\Factories;

use App\Models\InterviewRound;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewRound>
 */
class InterviewRoundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => JobPosting::factory(),
            'name' => fake()->unique()->words(2, true),
            'sequence_number' => fake()->unique()->numberBetween(1, 10000),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
