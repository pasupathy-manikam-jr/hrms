<?php

namespace Database\Factories;

use App\Models\AwardType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AwardType>
 */
class AwardTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Award',
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
