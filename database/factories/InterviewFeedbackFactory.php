<?php

namespace Database\Factories;

use App\Models\Interview;
use App\Models\InterviewFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewFeedback>
 */
class InterviewFeedbackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interview_id' => Interview::factory(),
            'overall_rating' => fake()->numberBetween(1, 5),
            'recommendation' => fake()->randomElement(InterviewFeedback::RECOMMENDATIONS),
            'comments' => fake()->sentence(),
        ];
    }
}
