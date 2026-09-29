<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d');

        return [
            'name' => fake()->unique()->word().' Day',
            'start_date' => $date,
            'end_date' => $date,
            'category' => fake()->randomElement(Holiday::CATEGORIES),
            'description' => fake()->sentence(),
            'is_paid' => true,
            'is_half_day' => false,
            'is_recurring' => false,
        ];
    }
}
