<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $current = fn (string $column) => fn (array $attributes) => Employee::query()->whereKey($attributes['employee_id'])->value($column);

        return [
            'employee_id' => Employee::factory(),
            'from_branch_id' => $current('branch_id'),
            'from_department_id' => $current('department_id'),
            'from_designation_id' => $current('designation_id'),
            'to_designation_id' => Designation::factory(),
            'to_department_id' => fn (array $attributes) => Designation::query()->whereKey($attributes['to_designation_id'])->value('department_id'),
            'to_branch_id' => fn (array $attributes) => Department::query()->whereKey($attributes['to_department_id'])->value('branch_id'),
            'transfer_date' => fake()->date(),
            'effective_date' => fake()->date(),
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
