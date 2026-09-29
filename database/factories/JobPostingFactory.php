<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\JobLocation;
use App\Models\JobPosting;
use App\Models\JobType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'job_type_id' => JobType::factory(),
            'location_id' => JobLocation::factory(),
            'department_id' => Department::factory(),
            'branch_id' => fn (array $attributes) => Department::query()->whereKey($attributes['department_id'])->value('branch_id'),
            'positions' => fake()->numberBetween(1, 5),
            'min_experience' => 1,
            'max_experience' => 5,
            'min_salary' => 30000,
            'max_salary' => 60000,
            'description' => fake()->paragraph(),
            'requirements' => fake()->sentence(),
            'skills' => ['PHP', 'Communication'],
            'start_date' => now()->addMonth()->toDateString(),
            'application_deadline' => now()->addWeeks(3)->toDateString(),
            'priority' => 'Medium',
            'status' => 'Draft',
        ];
    }
}
