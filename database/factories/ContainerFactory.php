<?php

namespace Database\Factories;

use App\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cbm_price' => fake()->randomFloat(2, 80, 200),
        ];
    }
}
