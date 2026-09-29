<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'department_id' => Department::factory(),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
