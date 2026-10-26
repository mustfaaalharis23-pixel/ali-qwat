<?php

namespace App\Orders\Checkout;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Orders\PayCustomerDebt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Checkout
{
    /**
     * The sale: checks the customer, the payment type, the items' prices, the down payment, and the weekly payment,
     * then saves the order, its items, and its down payment together, so either all of them are saved or none is.
     * Each item's price is its sale price from the database.
     *
     * @throws ValidationException
     */
    public function handle(User $user, CheckoutData $data): Order
    {
        $this->ensureCustomerExists($data);
        $this->ensureCustomerCanTakeAnOrder($data);
        $this->ensurePaymentTypeIsGiven($data);

        $items = $this->soldItems($data);
        $this->ensureItemsCanBeSold($items);
        $this->ensureDownPaymentFits($data, $items);
        $this->ensureWeeklyPaymentFits($data, $items);

        return DB::transaction(function () use ($user, $data, $items): Order {
            $order = $this->createOrder($user, $data);

            foreach ($data->lines as $line) {
                $this->addLine($order, $items->findOrFail($line->itemId), $line);
            }

            if ($data->downPayment !== null) {
                app(PayCustomerDebt::class)->downPayment($user, $order, $data->downPayment);
            }

            return $order;
        });
    }

    /**
     * A down payment is taken with a postpaid sale only, it is more than zero, and it is not more than what the order comes to.
     *
     * @param  Collection<int, Item>  $items
     *
     * @throws ValidationException
     */
    private function ensureDownPaymentFits(CheckoutData $data, Collection $items): void
    {
        if ($data->downPayment === null) {
            return;
        }

        if ($data->paymentType !== OrderPaymentType::Postpaid) {
            throw ValidationException::withMessages([
                'down_payment' => __('orders.validation.down_payment_postpaid_only'),
            ]);
        }

        if ($data->downPayment <= 0) {
            throw ValidationException::withMessages([
                'down_payment' => __('orders.validation.down_payment_must_be_positive'),
            ]);
        }

        if ($data->downPayment > $this->orderAmount($data, $items) + 0.0001) {
            throw ValidationException::withMessages([
                'down_payment' => __('orders.validation.down_payment_exceeds_order_amount'),
            ]);
        }
    }

    /**
     * A weekly payment is set on a postpaid sale only, it is more than zero, and it is not more than what is left
     * of the order after the down payment.
     *
     * @param  Collection<int, Item>  $items
     *
     * @throws ValidationException
     */
    private function ensureWeeklyPaymentFits(CheckoutData $data, Collection $items): void
    {
        if ($data->weeklyPayment === null) {
            return;
        }

        if ($data->paymentType !== OrderPaymentType::Postpaid) {
            throw ValidationException::withMessages([
                'weekly_payment' => __('orders.validation.weekly_payment_postpaid_only'),
            ]);
        }

        if ($data->weeklyPayment <= 0) {
            throw ValidationException::withMessages([
                'weekly_payment' => __('orders.validation.weekly_payment_must_be_positive'),
            ]);
        }

        if ($data->weeklyPayment > $this->orderAmount($data, $items) - ($data->downPayment ?? 0) + 0.0001) {
            throw ValidationException::withMessages([
                'weekly_payment' => __('orders.validation.weekly_payment_exceeds_remaining'),
            ]);
        }
    }

    /**
     * What the order comes to: each line's dozens at its item's sale price.
     *
     * @param  Collection<int, Item>  $items
     */
    private function orderAmount(CheckoutData $data, Collection $items): float
    {
        return array_sum(array_map(
            function (CheckoutLine $line) use ($items): float {
                $item = $items->findOrFail($line->itemId);

                return (float) $item->sale_price * $line->dozensFor($item);
            },
            $data->lines,
        ));
    }

    /**
     * An invoice-by-invoice customer takes a new order, cash or postpaid, only when they owe nothing.
     *
     * @throws ValidationException
     */
    private function ensureCustomerCanTakeAnOrder(CheckoutData $data): void
    {
        $customer = Customer::query()->findOrFail($data->customerId);

        if ($customer->is_invoice_by_invoice && $customer->debt() > 0.0001) {
            throw ValidationException::withMessages([
                'customer_id' => __('orders.validation.invoice_by_invoice_customer_has_debt'),
            ]);
        }
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
            'down_payment' => $data->downPayment,
            'weekly_payment' => $data->weeklyPayment,
        ]);
    }

    /**
     * Sells the line's packets of the item: their pieces are the packets × the item's pieces per packet, at the item's price of a dozen.
     * A line without packets is sold by its dozens. An item without a packet size is not sold by the packet.
     *
     * @throws ValidationException
     */
    private function addLine(Order $order, Item $item, CheckoutLine $line): OrderItem
    {
        if ($line->packets !== null && $item->pieces_per_packet === null) {
            throw ValidationException::withMessages([
                'packets' => __('orders.validation.item_has_no_packet', ['item' => $item->name]),
            ]);
        }

        return OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'lens_power_id' => $line->lensPowerId,
            'colors' => $line->colors,
            ...($line->packets === null
                ? ['quantity' => $line->quantity]
                : ['pieces' => $line->packets * (int) $item->pieces_per_packet]),
            'price' => $item->sale_price,
        ]);
    }
}
