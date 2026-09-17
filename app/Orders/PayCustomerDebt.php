<?php

namespace App\Orders;

use App\Enums\OrderPaymentType;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\SpecificDescription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayCustomerDebt
{
    /**
     * Records a payment of the customer and spreads it on the customer's postpaid orders that have a remaining, from the oldest to the newest.
     * The money moves from the postpaid box to the accountant's box: an input invoice with no cashier and no images,
     * with one line of the amount under the postpaid transfer description.
     * The actor needs customer_payment.create and invoice.create. The amount must be more than zero and not more than the customer's debt.
     * The debt is read and everything is saved while the customer's row is locked, so two payments at the same time run one after the other.
     * Either everything is saved or nothing is.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(
        User $actor,
        Customer $customer,
        float $amount,
        CarbonInterface|string $date,
        ?string $image = null,
        ?string $notes = null,
    ): CustomerPayment {
        Gate::forUser($actor)->authorize('create', CustomerPayment::class);
        Gate::forUser($actor)->authorize('create', Invoice::class);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('orders.validation.payment_amount_must_be_positive'),
            ]);
        }

        $description = SpecificDescription::forTransfer(OrderPaymentType::Postpaid);

        if ($description === null) {
            throw ValidationException::withMessages([
                'description' => __('orders.validation.payment_description_missing'),
            ]);
        }

        return DB::transaction(function () use ($actor, $customer, $amount, $date, $image, $notes, $description): CustomerPayment {
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

            $orders = $this->openOrders($customer);
            $debt = (float) $orders->sum('remaining');

            if ($amount > $debt + 0.0001) {
                $formattedDebt = FormattedValue::of($debt, Currency::IraqiDinar);

                throw ValidationException::withMessages([
                    'amount' => __('orders.validation.payment_exceeds_customer_debt', ['debt' => "{$formattedDebt->number} {$formattedDebt->currency}"]),
                ]);
            }

            $invoice = Invoice::create([
                'name' => "{$description->name} - {$customer->name}",
                'type' => $description->generalDescription->type,
                'general_description_id' => $description->general_description_id,
                'user_id' => $actor->id,
                'date' => $date,
                'images' => [],
                'notes' => $notes,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'specific_description_id' => $description->id,
                'price' => $amount,
            ]);

            $payment = CustomerPayment::create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'user_id' => $actor->id,
                'amount' => $amount,
                'date' => $date,
                'image' => $image,
                'notes' => $notes,
            ]);

            $this->spread($payment, $orders, $amount);

            return $payment;
        });
    }

    /**
     * The customer's whole remaining debt, from Customer::debt().
     */
    public function debtOf(Customer $customer): float
    {
        return $customer->debt();
    }

    /**
     * How the amount would be spread on the customer's debts, without saving anything: each order with its share,
     * from the oldest to the newest. It is the same spreading a payment uses, so the payment form can show it before saving.
     *
     * @return list<array{order: Order, share: float}>
     */
    public function plan(Customer $customer, float $amount): array
    {
        return $this->shares($this->openOrders($customer), $amount);
    }

    /**
     * Saves one allocation for each share of the amount.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function spread(CustomerPayment $payment, Collection $orders, float $amount): void
    {
        foreach ($this->shares($orders, $amount) as $line) {
            CustomerPaymentOrder::create([
                'customer_payment_id' => $payment->id,
                'order_id' => $line['order']->id,
                'amount' => $line['share'],
            ]);
        }
    }

    /**
     * Gives each order up to its remaining, in the given order, until the amount is used.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<array{order: Order, share: float}>
     */
    private function shares(Collection $orders, float $amount): array
    {
        $left = $amount;
        $shares = [];

        foreach ($orders as $order) {
            if ($left <= 0.0001) {
                break;
            }

            $share = min($left, (float) $order->remaining);
            $shares[] = ['order' => $order, 'share' => $share];
            $left -= $share;
        }

        return $shares;
    }

    /**
     * The customer's debts, from the oldest date to the newest. Deleted orders are not included.
     *
     * @return Collection<int, Order>
     */
    private function openOrders(Customer $customer): Collection
    {
        return Order::query()
            ->debts()
            ->where('customer_id', $customer->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }
}
