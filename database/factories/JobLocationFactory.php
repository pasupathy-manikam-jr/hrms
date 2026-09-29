<?php

namespace Database\Factories;

use App\Models\JobLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobLocation>
 */
class JobLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Office - '.fake()->unique()->city(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->city(),
            'country' => fake()->country(),
            'postal_code' => fake()->postcode(),
            'is_remote' => false,
            'status' => 'active',
        ];
    }
}
