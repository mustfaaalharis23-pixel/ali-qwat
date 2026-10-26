<?php

namespace Database\Factories;

use App\Models\LensPower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensPower>
 */
class LensPowerFactory extends Factory
{
    /**
     * Define the model's default state: a power in steps of a quarter, like the real ones.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => fake()->unique()->numberBetween(1, 2000) / 4,
        ];
    }
}
