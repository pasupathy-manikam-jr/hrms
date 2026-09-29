<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Resignation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resignation>
 */
class ResignationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'resignation_date' => now()->toDateString(), 'last_working_day' => now()->addMonth()->toDateString(), 'notice_period' => '1 month', 'reason' => fake()->sentence(3), 'status' => 'pending',
        ];
    }
}
