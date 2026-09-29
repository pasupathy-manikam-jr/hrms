<?php

namespace Database\Factories;

use App\Models\CandidateSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateSource>
 */
class CandidateSourceFactory extends Factory
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
