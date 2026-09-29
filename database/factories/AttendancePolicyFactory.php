<?php

namespace Database\Factories;

use App\Models\AttendancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePolicy>
 */
class AttendancePolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Policy',
            'description' => fake()->sentence(),
            'late_arrival_grace' => 15,
            'early_departure_grace' => 15,
            'half_day_threshold' => 4,
            'overtime_rate_per_hour' => 150,
            'status' => 'active',
        ];
    }
}
