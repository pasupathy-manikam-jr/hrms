<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeReview;
use App\Models\ReviewCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeReview>
 */
class EmployeeReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'review_cycle_id' => ReviewCycle::factory(),
            'review_date' => '2026-03-01',
            'status' => 'scheduled',
        ];
    }
}
