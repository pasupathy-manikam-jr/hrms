<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\AwardType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'award_type_id' => AwardType::factory(),
            'award_date' => fake()->date(),
            'gift' => fake()->randomElement(['Trophy', 'Plaque', 'Gift Voucher']),
            'monetary_value' => fake()->optional()->randomFloat(2, 100, 5000),
            'description' => fake()->sentence(),
        ];
    }
}
