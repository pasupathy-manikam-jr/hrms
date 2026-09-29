<?php

namespace Database\Factories;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'previous_designation_id' => fn (array $attributes) => Employee::query()->whereKey($attributes['employee_id'])->value('designation_id'),
            'designation_id' => Designation::factory(),
            'promotion_date' => fake()->date(),
            'effective_date' => fake()->date(),
            'salary_adjustment' => fake()->randomFloat(2, 1000, 20000),
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
