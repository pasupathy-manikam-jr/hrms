<?php

namespace Database\Factories;

use App\Models\MeetingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingType>
 */
class MeetingTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Meeting',
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'default_duration' => fake()->randomElement([30, 45, 60, 90]),
            'status' => 'active',
        ];
    }
}
