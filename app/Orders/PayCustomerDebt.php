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

        $description = $this->transferDescription();

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

            $payment = $this->recordPayment($actor, $customer, $description, $amount, $date, $image, $notes);

            $this->spread($payment, $orders, $amount);

            return $payment;
        });
    }

    /**
     * Records a payment of the customer on the orders the seller chose, each with its own amount ([order id => amount]), instead of
     * spreading it from the oldest, so every order keeps what was paid on it. The payment is the amounts together, and its money
     * moves the same way as any payment. Every order must be one of the customer's postpaid orders with something left on it,
     * and every amount more than zero and not more than what is left on its order.
     * The actor needs customer_payment.create and invoice.create. Everything is saved while the customer's row is locked,
     * and either everything is saved or nothing is.
     *
     * @param  array<int|string, float|int|string>  $amounts
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function payOrders(
        User $actor,
        Customer $customer,
        array $amounts,
        CarbonInterface|string $date,
        ?string $image = null,
        ?string $notes = null,
    ): CustomerPayment {
        Gate::forUser($actor)->authorize('create', CustomerPayment::class);
        Gate::forUser($actor)->authorize('create', Invoice::class);

        $amounts = array_map(fn (mixed $amount): float => (float) $amount, $amounts);

        if ($amounts === [] || collect($amounts)->contains(fn (float $amount): bool => $amount <= 0)) {
            throw ValidationException::withMessages([
                'amount' => __('orders.validation.payment_amount_must_be_positive'),
            ]);
        }

        $description = $this->transferDescription();

        return DB::transaction(function () use ($actor, $customer, $amounts, $date, $image, $notes, $description): CustomerPayment {
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

            $orders = $this->openOrders($customer)->keyBy('id');

            foreach ($amounts as $orderId => $amount) {
                $this->ensureFitsTheOrder($orders->get((int) $orderId), $amount);
            }

            $payment = $this->recordPayment($actor, $customer, $description, array_sum($amounts), $date, $image, $notes);

            foreach ($amounts as $orderId => $amount) {
                CustomerPaymentOrder::create([
                    'customer_payment_id' => $payment->id,
                    'order_id' => (int) $orderId,
                    'amount' => $amount,
                ]);
            }

            return $payment;
        });
    }

    /**
     * Records the down payment of a postpaid order sold just now: a payment marked as a down payment, by the seller,
     * on the order's date, paying this order only and not the customer's older debts. The money moves the same way as any payment,
     * through a postpaid money withdrawal invoice. It is taken with the sale itself, so it needs no payment permission.
     *
     * @throws ValidationException
     */
    public function downPayment(User $seller, Order $order, float $amount): CustomerPayment
    {
        $description = $this->transferDescription();

        return DB::transaction(function () use ($seller, $order, $amount, $description): CustomerPayment {
            $payment = CustomerPayment::create([
                'customer_id' => $order->customer_id,
                'invoice_id' => $this->withdrawalInvoice($seller, $order->customer, $description, $amount, $order->date, null)->id,
                'user_id' => $seller->id,
                'amount' => $amount,
                'is_down_payment' => true,
                'date' => $order->date,
            ]);

            CustomerPaymentOrder::create([
                'customer_payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $amount,
            ]);

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
     * An amount paid on a chosen order needs the order to be one of the customer's debts, and it is not more than what is left on it.
     *
     * @throws ValidationException
     */
    private function ensureFitsTheOrder(?Order $order, float $amount): void
    {
        if ($order === null) {
            throw ValidationException::withMessages([
                'orders' => __('orders.validation.payment_order_not_open'),
            ]);
        }

        if ($amount > (float) $order->remaining + 0.0001) {
            $remaining = FormattedValue::of((float) $order->remaining, Currency::IraqiDinar);

            throw ValidationException::withMessages([
                'orders' => __('orders.validation.payment_exceeds_order_remaining', [
                    'order' => $order->id,
                    'remaining' => "{$remaining->number} {$remaining->currency}",
                ]),
            ]);
        }
    }

    /**
     * Saves the payment with its postpaid money withdrawal invoice.
     */
    private function recordPayment(
        User $actor,
        Customer $customer,
        SpecificDescription $description,
        float $amount,
        CarbonInterface|string $date,
        ?string $image,
        ?string $notes,
    ): CustomerPayment {
        return CustomerPayment::create([
            'customer_id' => $customer->id,
            'invoice_id' => $this->withdrawalInvoice($actor, $customer, $description, $amount, $date, $notes)->id,
            'user_id' => $actor->id,
            'amount' => $amount,
            'date' => $date,
            'image' => $image,
            'notes' => $notes,
        ]);
    }

    /**
     * The description that postpaid money moves under.
     *
     * @throws ValidationException
     */
    private function transferDescription(): SpecificDescription
    {
        return SpecificDescription::forTransfer(OrderPaymentType::Postpaid)
            ?? throw ValidationException::withMessages([
                'description' => __('orders.validation.payment_description_missing'),
            ]);
    }

    /**
     * The input invoice that moves the amount from the postpaid box to the accountant's box: no cashier, no images,
     * and one line of the amount under the postpaid transfer description.
     */
    private function withdrawalInvoice(
        User $actor,
        Customer $customer,
        SpecificDescription $description,
        float $amount,
        CarbonInterface|string $date,
        ?string $notes,
    ): Invoice {
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

        return $invoice;
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
