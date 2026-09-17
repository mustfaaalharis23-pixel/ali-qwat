<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Orders\PayCustomerDebt;
use Illuminate\Database\Seeder;

class CustomerPaymentSeeder extends Seeder
{
    /**
     * Fake customer payments for local development, after the orders are seeded, saved through PayCustomerDebt,
     * so the orders' remaining, the postpaid box, and the accountant's balance follow them.
     * Every customer with a debt pays half of it, rounded down to a thousand, dated today, recorded by the first super user.
     * When there is no super user, or a customer's half is less than a thousand, nothing is paid for it.
     */
    public function run(PayCustomerDebt $payCustomerDebt): void
    {
        $user = User::query()->where('is_super', true)->first();

        if ($user === null) {
            return;
        }

        foreach (Customer::query()->get() as $customer) {
            $amount = floor($customer->debt() / 2 / 1000) * 1000;

            if ($amount <= 0) {
                continue;
            }

            $payCustomerDebt->handle($user, $customer, $amount, today());
        }
    }
}
