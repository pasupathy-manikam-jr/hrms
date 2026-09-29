<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'type_id' => MeetingType::factory(),
            'room_id' => null,
            'meeting_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'duration' => 60,
            'agenda' => fake()->sentence(),
            'status' => 'Scheduled',
            'recurrence' => 'None',
            'recurrence_end_date' => null,
            'organizer_id' => User::factory(),
        ];
    }
}
