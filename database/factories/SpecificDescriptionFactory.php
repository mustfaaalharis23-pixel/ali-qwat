<?php

namespace Database\Factories;

use App\Models\GeneralDescription;
use App\Models\SpecificDescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecificDescription>
 */
class SpecificDescriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'general_description_id' => GeneralDescription::factory(),
            'name' => fake()->words(2, true),
        ];
    }
}
