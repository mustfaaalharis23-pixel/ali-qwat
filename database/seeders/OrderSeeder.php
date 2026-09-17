<?php

namespace Database\Seeders;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Fake orders for local development, saved through the checkout so the prices come from the items' sale prices.
     * Every cashier gets six orders, cash and postpaid in turn, so each cashier has sales in both boxes.
     * Only active items that can be sold and have stock are used, and nothing is sold for more than what is left of it;
     * when there are none, or no customers or cashiers, nothing is seeded.
     */
    public function run(Checkout $checkout): void
    {
        $cashiers = User::query()->where('is_cashier', true)->get();
        $customers = Customer::query()->get();
        $items = Item::query()
            ->where('is_active', true)
            ->where('sale_price', '>', 0)
            ->whereColumn('sale_price', '>=', 'cost_price')
            ->where('available_quantity', '>', 0)
            ->get();

        if ($cashiers->isEmpty() || $customers->isEmpty() || $items->isEmpty()) {
            return;
        }

        foreach ($cashiers as $cashier) {
            foreach (range(1, 6) as $number) {
                $lines = $items->shuffle()
                    ->take(random_int(1, min(3, $items->count())))
                    ->map(fn (Item $item): ?CheckoutLine => $this->line($item))
                    ->filter()
                    ->values()
                    ->all();

                if ($lines === []) {
                    continue;
                }

                $checkout->handle($cashier, new CheckoutData(
                    customerId: $customers->random()->id,
                    paymentType: $number % 2 === 0 ? OrderPaymentType::Postpaid : OrderPaymentType::Cash,
                    date: today()->subDays(random_int(0, 30)),
                    lines: $lines,
                    notes: $number % 3 === 0 ? fake()->sentence() : null,
                ));
            }
        }
    }

    /**
     * A line of a few units of the item, never more than what is left of it, or nothing when it ran out.
     */
    private function line(Item $item): ?CheckoutLine
    {
        $available = (int) $item->fresh()->available_quantity;

        return $available < 1 ? null : new CheckoutLine($item->id, random_int(1, min(3, $available)));
    }
}
