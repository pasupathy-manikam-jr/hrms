<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => fake()->unique()->dateTimeBetween('-60 days', '-1 day')->format('Y-m-d'),
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'total_hours' => 8,
            'status' => 'present',
            'is_late' => false,
            'is_early_departure' => false,
            'overtime_hours' => 0,
            'notes' => null,
        ];
    }
}
