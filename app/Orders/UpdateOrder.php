<?php

namespace App\Orders;

use App\Enums\OrderPaymentType;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrder
{
    /**
     * Edits a saved order: its customer, payment type, date, notes, weekly payment, and lines. A line is matched to the saved one
     * of the same item, power, and colors, which keeps the price it was sold at; a new line takes its item's sale price of now, and
     * a line that is not sent anymore is deleted, so its pieces go back to the stock. The stock rules of a sale apply to every line,
     * and the order's discount is spread again over its lines as they are after the edit.
     * An order with payments on it (its down payment too) keeps its customer and its payment type, and never drops below what was paid.
     * Either everything is saved or nothing is.
     *
     * @throws ValidationException
     */
    public function handle(Order $order, CheckoutData $data): Order
    {
        $this->ensureCustomerExists($data);
        $this->ensurePaymentTypeIsGiven($data);
        $this->ensureHasLines($data);

        $hasPayments = $order->allocations()->exists();

        if ($hasPayments) {
            $this->ensureKeepsCustomerAndPaymentType($order, $data);
        }

        $this->ensureNewCustomerCanTakeTheOrder($order, $data);

        $savedLines = $order->items()->get();
        $items = Item::query()->findOrFail(array_values(array_unique(array_map(fn (CheckoutLine $line): int => $line->itemId, $data->lines))));
        [$keptLines, $newLines] = $this->matchLines($savedLines, $data->lines);
        $this->ensureItemsCanBeSold($items->whereIn('id', array_map(fn (CheckoutLine $line): int => $line->itemId, $newLines)));

        return DB::transaction(function () use ($order, $data, $savedLines, $items, $keptLines, $newLines): Order {
            $order->update([
                'customer_id' => $data->customerId,
                'payment_type' => $data->paymentType,
                'date' => $data->date,
                'notes' => $data->notes,
                'weekly_payment' => $data->weeklyPayment,
            ]);

            foreach ($keptLines as $lineId => $line) {
                $this->fillLine($savedLines->find($lineId), $items->find($line->itemId), $line)->save();
            }

            foreach ($newLines as $line) {
                $item = $items->find($line->itemId);

                $this->fillLine(new OrderItem([
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'lens_power_id' => $line->lensPowerId,
                    'colors' => $line->colors,
                    'price' => $item->sale_price,
                ]), $item, $line)->save();
            }

            $savedLines->whereNotIn('id', array_keys($keptLines))->each(fn (OrderItem $line): ?bool => $line->delete());

            $order->refresh()->recalculateAmounts();
            $order->applyDiscount($data->discount);

            $this->ensureNotBelowWhatWasPaid($order);
            $this->ensureWeeklyPaymentFits($order);

            return $order;
        });
    }

    /**
     * Splits the sent lines into the ones that match a saved line, keyed by that line's id, and the new ones.
     * A saved line matches one sent line only: the one of the same item, power, and colors.
     *
     * @param  Collection<int, OrderItem>  $savedLines
     * @param  list<CheckoutLine>  $lines
     * @return array{array<int, CheckoutLine>, list<CheckoutLine>}
     */
    private function matchLines(Collection $savedLines, array $lines): array
    {
        $kept = [];
        $new = [];

        foreach ($lines as $line) {
            $match = $savedLines->first(fn (OrderItem $saved): bool => ! isset($kept[$saved->id])
                && (int) $saved->item_id === $line->itemId
                && Item::packetTypeKey($saved->lens_power_id, $saved->colors) === Item::packetTypeKey($line->lensPowerId, $line->colors));

            if ($match === null) {
                $new[] = $line;
            } else {
                $kept[$match->id] = $line;
            }
        }

        return [$kept, $new];
    }

    /**
     * Puts the line's packets on the sold line as pieces (the packets × the item's pieces per packet), or its dozens for a line
     * without packets. An item without a packet size is not sold by the packet.
     *
     * @throws ValidationException
     */
    private function fillLine(OrderItem $orderItem, Item $item, CheckoutLine $line): OrderItem
    {
        if ($line->packets === null) {
            $orderItem->quantity = $line->quantity;

            return $orderItem;
        }

        if ($item->pieces_per_packet === null) {
            throw ValidationException::withMessages([
                'packets' => __('orders.validation.item_has_no_packet', ['item' => $item->name]),
            ]);
        }

        $orderItem->pieces = $line->packets * (int) $item->pieces_per_packet;

        return $orderItem;
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
     * There is no order without lines.
     *
     * @throws ValidationException
     */
    private function ensureHasLines(CheckoutData $data): void
    {
        if ($data->lines === []) {
            throw ValidationException::withMessages([
                'items' => __('orders.validation.order_needs_items'),
            ]);
        }
    }

    /**
     * The payments on an order belong to its customer and are postpaid money, so the order keeps both.
     *
     * @throws ValidationException
     */
    private function ensureKeepsCustomerAndPaymentType(Order $order, CheckoutData $data): void
    {
        if ($data->customerId !== (int) $order->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => __('orders.validation.paid_order_keeps_customer'),
            ]);
        }

        if ($data->paymentType !== $order->payment_type) {
            throw ValidationException::withMessages([
                'payment_type' => __('orders.validation.paid_order_keeps_payment_type'),
            ]);
        }
    }

    /**
     * An order moved to an invoice-by-invoice customer is a new order for them, so they take it only when they owe nothing.
     * The order's own customer is not checked: the debt they carry is the order itself.
     *
     * @throws ValidationException
     */
    private function ensureNewCustomerCanTakeTheOrder(Order $order, CheckoutData $data): void
    {
        if ($data->customerId === (int) $order->customer_id) {
            return;
        }

        $customer = Customer::query()->findOrFail($data->customerId);

        if ($customer->is_invoice_by_invoice && $customer->debt() > 0.0001) {
            throw ValidationException::withMessages([
                'customer_id' => __('orders.validation.invoice_by_invoice_customer_has_debt'),
            ]);
        }
    }

    /**
     * A new line's item needs a sale price, and it cannot be sold for less than its cost price.
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

    /**
     * The order is never less than what was paid on it.
     *
     * @throws ValidationException
     */
    private function ensureNotBelowWhatWasPaid(Order $order): void
    {
        if ($order->remaining >= -0.0001) {
            return;
        }

        $paid = FormattedValue::of($order->paid_amount, Currency::IraqiDinar);

        throw ValidationException::withMessages([
            'items' => __('orders.validation.order_below_paid_amount', ['paid' => "{$paid->number} {$paid->currency}"]),
        ]);
    }

    /**
     * A weekly payment is set on a postpaid order only, it is more than zero, and it is not more than what is left
     * of the order after its down payment.
     *
     * @throws ValidationException
     */
    private function ensureWeeklyPaymentFits(Order $order): void
    {
        if ($order->weekly_payment === null) {
            return;
        }

        if ($order->payment_type !== OrderPaymentType::Postpaid) {
            throw ValidationException::withMessages([
                'weekly_payment' => __('orders.validation.weekly_payment_postpaid_only'),
            ]);
        }

        if ($order->weekly_payment <= 0) {
            throw ValidationException::withMessages([
                'weekly_payment' => __('orders.validation.weekly_payment_must_be_positive'),
            ]);
        }

        if ($order->weekly_payment > $order->total_amount - ($order->down_payment ?? 0) + 0.0001) {
            throw ValidationException::withMessages([
                'weekly_payment' => __('orders.validation.weekly_payment_exceeds_remaining'),
            ]);
        }
    }
}
