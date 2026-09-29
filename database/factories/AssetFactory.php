<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(5, 500) * 1000;

        return [
            'name' => fake()->unique()->words(2, true),
            'asset_type_id' => AssetType::factory(),
            'serial_number' => fake()->bothify('??####'),
            'asset_code' => fake()->unique()->bothify('AST-#####'),
            'purchase_date' => fake()->date(max: 'now'),
            'purchase_cost' => $cost,
            'salvage_value' => $cost / 10,
            'useful_life_years' => 5,
            'status' => 'available',
            'condition' => 'good',
            'location' => 'Main Office',
        ];
    }
}
