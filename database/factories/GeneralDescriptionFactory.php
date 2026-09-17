<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\GeneralDescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneralDescription>
 */
class GeneralDescriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(InvoiceType::cases()),
        ];
    }
}
