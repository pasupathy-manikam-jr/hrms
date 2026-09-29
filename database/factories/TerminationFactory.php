<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Termination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Termination>
 */
class TerminationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'termination_type' => 'retirement', 'notice_date' => now()->toDateString(), 'termination_date' => now()->addMonth()->toDateString(), 'reason' => fake()->sentence(3), 'status' => 'planned',
        ];
    }
}
