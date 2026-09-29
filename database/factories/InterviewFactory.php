<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Interview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'job_id' => fn (array $attributes) => Candidate::query()->whereKey($attributes['candidate_id'])->value('job_id'),
            'scheduled_date' => now()->addWeek()->toDateString(),
            'scheduled_time' => '10:00:00',
            'duration' => 60,
            'location' => 'Conference Room A',
            'status' => 'Scheduled',
        ];
    }
}
