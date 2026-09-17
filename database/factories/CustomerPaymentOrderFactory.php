<?php

namespace Database\Factories;

use App\Enums\OrderPaymentType;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPaymentOrder>
 */
class CustomerPaymentOrderFactory extends Factory
{
    /**
     * Define the model's default state: a share of 100 on a new postpaid order of 1000.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_payment_id' => CustomerPayment::factory(),
            'order_id' => fn (): int => OrderItem::factory()->create([
                'order_id' => Order::factory()->state(['payment_type' => OrderPaymentType::Postpaid]),
                'quantity' => 1,
                'price' => 1000,
            ])->order_id,
            'amount' => 100,
        ];
    }
}
