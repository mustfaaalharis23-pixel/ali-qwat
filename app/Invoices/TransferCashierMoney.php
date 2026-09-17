<?php

namespace App\Invoices;

use App\Enums\OrderPaymentType;
use App\Filament\Pages\FinancialDashboard;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\SpecificDescription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransferCashierMoney
{
    /**
     * Moves money from a cashier to the accountant's box: an input invoice on the cashier, with no images,
     * and one line of the amount under the cash transfer description.
     * The actor needs both the transfer permission and invoice.create. The amount must be more than zero and never more
     * than the money on the cashier, so the cashier's box never goes below zero.
     * The money on the cashier is read and the invoice is saved while the cashier's row is locked, so two transfers at the same time
     * run one after the other and the second one sees the first. Either everything is saved or nothing is.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $cashier, float $amount, CarbonInterface|string $date, ?string $notes = null): Invoice
    {
        Gate::forUser($actor)->authorize('transferCashierMoney', FinancialDashboard::class);
        Gate::forUser($actor)->authorize('create', Invoice::class);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('invoices.validation.transfer_amount_must_be_positive'),
            ]);
        }

        if (! $cashier->is_cashier) {
            throw ValidationException::withMessages([
                'cashier' => __('invoices.validation.transfer_user_not_a_cashier'),
            ]);
        }

        $description = $this->description();

        if ($description === null) {
            throw ValidationException::withMessages([
                'description' => __('invoices.validation.transfer_description_missing'),
            ]);
        }

        return DB::transaction(function () use ($actor, $cashier, $amount, $date, $notes, $description): Invoice {
            User::query()->whereKey($cashier->getKey())->lockForUpdate()->first();

            $moneyOnCashier = $this->moneyOn($cashier);

            if ($amount > $moneyOnCashier + 0.0001) {
                $money = FormattedValue::of($moneyOnCashier, Currency::IraqiDinar);

                throw ValidationException::withMessages([
                    'amount' => __('invoices.validation.transfer_exceeds_money_on_cashier', ['money' => "{$money->number} {$money->currency}"]),
                ]);
            }

            $invoice = Invoice::create([
                'name' => "{$description->name} - {$cashier->name}",
                'type' => $description->generalDescription->type,
                'general_description_id' => $description->general_description_id,
                'user_id' => $actor->id,
                'cashier_id' => $cashier->id,
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
        });
    }

    /**
     * The specific description a transfer is saved under, from SpecificDescription::forTransfer() with cash.
     */
    public function description(): ?SpecificDescription
    {
        return SpecificDescription::forTransfer(OrderPaymentType::Cash);
    }

    /**
     * The money on the cashier now, which is not transferred yet: the cashier's cash sales minus the cash transferred, of every date.
     */
    public function moneyOn(User $cashier): float
    {
        return Order::boxBalance(OrderPaymentType::Cash, new Period(null, null), $cashier);
    }
}
