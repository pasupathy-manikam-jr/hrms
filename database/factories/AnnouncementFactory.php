<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(Announcement::CATEGORIES),
            'description' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'start_date' => today()->subDay()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
            'is_featured' => false,
            'is_high_priority' => false,
            'is_company_wide' => true,
        ];
    }
}
