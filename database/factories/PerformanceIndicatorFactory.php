<?php

namespace Database\Factories;

use App\Models\PerformanceIndicator;
use App\Models\PerformanceIndicatorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceIndicator>
 */
class PerformanceIndicatorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => PerformanceIndicatorCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'measurement_unit' => 'Rating',
            'target_value' => '4/5',
            'status' => 'active',
        ];
    }
}
