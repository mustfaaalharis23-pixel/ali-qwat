<?php

namespace Database\Factories;

use App\Enums\CustomerPaymentType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'payment_type' => fake()->randomElement(CustomerPaymentType::cases()),
        ];
    }
}
