<?php

namespace Database\Factories;

use App\Models\OfferTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferTemplate>
 */
class OfferTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'template_content' => 'Dear {{candidate_name}}, we offer you {{job_title}} at {{salary}} from {{start_date}}.',
            'status' => 'active',
        ];
    }
}
