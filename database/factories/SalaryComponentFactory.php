<?php

namespace Database\Factories;

use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryComponent>
 */
class SalaryComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Allowance',
            'description' => fake()->sentence(),
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_amount' => fake()->randomFloat(2, 100, 5000),
            'percentage_of_basic' => null,
            'is_taxable' => false,
            'is_mandatory' => false,
            'status' => 'active',
        ];
    }

    public function deduction(): static
    {
        return $this->state(['type' => 'deduction']);
    }

    public function percentage(string $percentage): static
    {
        return $this->state(['calculation_type' => 'percentage', 'default_amount' => 0, 'percentage_of_basic' => $percentage]);
    }
}
