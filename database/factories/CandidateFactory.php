<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateSource;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => JobPosting::factory(),
            'source_id' => CandidateSource::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'experience_years' => fake()->numberBetween(0, 15),
            'expected_salary' => 50000,
            'status' => 'New',
            'application_date' => now()->toDateString(),
        ];
    }
}
