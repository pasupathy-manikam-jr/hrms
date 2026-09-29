<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Branch',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->city(),
            'country' => fake()->country(),
            'zip_code' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'status' => 'active',
        ];
    }
}
