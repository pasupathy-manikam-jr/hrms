<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Shift',
            'description' => fake()->sentence(),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration' => 60,
            'break_start_time' => '13:00',
            'break_end_time' => '14:00',
            'grace_period' => 15,
            'is_night_shift' => false,
            'status' => 'active',
        ];
    }
}
