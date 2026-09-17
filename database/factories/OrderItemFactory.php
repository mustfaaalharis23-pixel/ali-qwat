<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state: a line of a new order, for an item that has stock, because an item is never sold without stock.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'item_id' => Item::factory()->stocked(),
            'quantity' => fake()->numberBetween(1, 10),
            'price' => fake()->numberBetween(250, 50000),
        ];
    }
}
