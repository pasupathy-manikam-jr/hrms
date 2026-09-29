<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'job_id' => fn (array $attributes) => Candidate::query()->whereKey($attributes['candidate_id'])->value('job_id'),
            'offer_date' => now()->toDateString(),
            'salary' => '75000.00',
            'start_date' => now()->addMonth()->toDateString(),
            'expiration_date' => now()->addWeeks(2)->toDateString(),
            'status' => 'Draft',
        ];
    }
}
