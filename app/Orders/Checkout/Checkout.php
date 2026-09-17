<?php

namespace App\Orders\Checkout;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Checkout
{
    /**
     * The sale: checks the customer, the payment type, and the items' prices, then saves the order and its items together,
     * so either all of them are saved or none is. Each item's price is its sale price from the database.
     *
     * @throws ValidationException
     */
    public function handle(User $user, CheckoutData $data): Order
    {
        $this->ensureCustomerExists($data);
        $this->ensurePaymentTypeIsGiven($data);

        $items = $this->soldItems($data);
        $this->ensureItemsCanBeSold($items);

        return DB::transaction(function () use ($user, $data, $items): Order {
            $order = $this->createOrder($user, $data);

            foreach ($data->lines as $line) {
                $this->addLine($order, $items->findOrFail($line->itemId), $line->quantity);
            }

            return $order;
        });
    }

    /**
     * There is no order without a customer, and a missing or deleted customer is rejected.
     *
     * @throws ValidationException
     */
    private function ensureCustomerExists(CheckoutData $data): void
    {
        if ($data->customerId === null || ! Customer::query()->whereKey($data->customerId)->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => __('orders.validation.customer_required'),
            ]);
        }
    }

    /**
     * An order without a payment type does not enter the system.
     *
     * @throws ValidationException
     */
    private function ensurePaymentTypeIsGiven(CheckoutData $data): void
    {
        if ($data->paymentType === null) {
            throw ValidationException::withMessages([
                'payment_type' => __('orders.validation.payment_type_required'),
            ]);
        }
    }

    /**
     * The items of the checkout lines, loaded once from the database.
     *
     * @return Collection<int, Item>
     */
    private function soldItems(CheckoutData $data): Collection
    {
        $itemIds = array_values(array_unique(array_map(fn (CheckoutLine $line): int => $line->itemId, $data->lines)));

        return Item::query()->findOrFail($itemIds);
    }

    /**
     * Every item needs a sale price, and it cannot be sold for less than its cost price.
     * One item that breaks this rejects the whole order.
     *
     * @param  Collection<int, Item>  $items
     *
     * @throws ValidationException
     */
    private function ensureItemsCanBeSold(Collection $items): void
    {
        foreach ($items as $item) {
            if ($item->sale_price <= 0) {
                throw ValidationException::withMessages([
                    'items' => __('orders.validation.item_without_sale_price', ['item' => $item->name]),
                ]);
            }

            if ($item->sale_price < $item->cost_price) {
                throw ValidationException::withMessages([
                    'items' => __('orders.validation.item_sale_price_below_cost_price', ['item' => $item->name]),
                ]);
            }
        }
    }

    private function createOrder(User $user, CheckoutData $data): Order
    {
        return Order::create([
            'customer_id' => $data->customerId,
            'user_id' => $user->id,
            'cashier_id' => $user->id,
            'payment_type' => $data->paymentType,
            'date' => $data->date,
            'notes' => $data->notes,
        ]);
    }

    private function addLine(Order $order, Item $item, float $quantity): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'price' => $item->sale_price,
        ]);
    }
}
