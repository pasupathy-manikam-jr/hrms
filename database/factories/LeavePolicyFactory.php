<?php

namespace Database\Factories;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeavePolicy>
 */
class LeavePolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Leave Policy',
            'description' => fake()->sentence(),
            'leave_type_id' => LeaveType::factory(),
            'accrual_type' => 'yearly',
            'accrual_rate' => 12,
            'carry_forward_limit' => 0,
            'min_days_per_application' => 1,
            'max_days_per_application' => 30,
            'requires_approval' => true,
            'status' => 'active',
        ];
    }
}
