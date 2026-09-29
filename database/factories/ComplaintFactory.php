<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'complaint_type' => 'Harassment', 'subject' => fake()->sentence(3), 'complaint_date' => now()->toDateString(), 'description' => fake()->paragraph(), 'status' => 'submitted',
        ];
    }
}
