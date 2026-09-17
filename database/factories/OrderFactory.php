<?php

namespace Database\Factories;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'cashier_id' => User::factory()->cashier(),
            'payment_type' => fake()->randomElement(OrderPaymentType::cases()),
            'date' => fake()->date(),
        ];
    }
}
