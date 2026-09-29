<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'purpose' => fake()->sentence(2), 'destination' => fake()->city(), 'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(), 'status' => 'planned',
        ];
    }
}
