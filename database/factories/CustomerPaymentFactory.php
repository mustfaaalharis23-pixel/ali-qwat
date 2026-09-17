<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    /**
     * Define the model's default state: a payment of 100 today, with no allocations.
     * A real payment is recorded through PayCustomerDebt, which also spreads it on the orders and creates its withdrawal invoice.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'invoice_id' => Invoice::factory(),
            'user_id' => User::factory(),
            'amount' => 100,
            'date' => today(),
        ];
    }
}
