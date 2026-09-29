<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApplication>
 */
class LeaveApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A Monday-to-Wednesday block: 3 working days under the default Mon-Fri week.
        $start = now()->startOfWeek()->addWeeks(fake()->unique()->numberBetween(2, 200));

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory()->state(['max_days_per_year' => 30]),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'total_days' => 3,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
