<?php

namespace Database\Factories;

use App\Models\MeetingRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingRoom>
 */
class MeetingRoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lastName().' Room',
            'description' => fake()->sentence(),
            'type' => 'Physical',
            'location' => fake()->streetAddress(),
            'capacity' => fake()->numberBetween(4, 30),
            'equipment' => ['Whiteboard', 'WiFi'],
            'booking_url' => null,
            'status' => 'active',
        ];
    }
}
