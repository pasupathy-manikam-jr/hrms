<?php

namespace Database\Factories;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionItem>
 */
class ActionItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'assigned_to' => User::factory(),
            'due_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'priority' => 'Medium',
            'status' => 'Not Started',
            'progress_percentage' => 0,
            'notes' => null,
            'completed_date' => null,
        ];
    }
}
