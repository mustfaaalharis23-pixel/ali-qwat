<?php

namespace App\Invoices;

use App\Models\Invoice;
use App\Models\Payoff;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class DistributeSupplierPayoff
{
    /**
     * Splits one amount into payoffs on the supplier's purchase invoices that have a remaining, from the oldest to the newest.
     * Each invoice gets up to its remaining. The amount must be more than zero and not more than the supplier's whole debt,
     * and either every payoff is saved or none is.
     *
     * @return Collection<int, Payoff>
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Supplier $supplier, float $amount, CarbonInterface|string $date): Collection
    {
        Gate::forUser($actor)->authorize('create', Payoff::class);

        $invoices = $this->openInvoices($supplier);
        $debt = (float) $invoices->sum('remaining');

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('invoices.validation.payoff_amount_must_be_positive'),
            ]);
        }

        if ($amount > $debt + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('invoices.validation.payoff_exceeds_supplier_debt', ['debt' => Number::format($debt, precision: 2)]),
            ]);
        }

        return DB::transaction(function () use ($invoices, $amount, $date): Collection {
            $payoffs = new Collection;
            $left = $amount;

            foreach ($invoices as $invoice) {
                if ($left <= 0.0001) {
                    break;
                }

                $share = min($left, (float) $invoice->remaining);

                $payoffs->push(Payoff::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $share,
                    'date' => $date,
                ]));

                $left -= $share;
            }

            return $payoffs;
        });
    }

    /**
     * The whole remaining debt of the supplier, from Supplier::totalDebt().
     */
    public function debtOf(Supplier $supplier): float
    {
        return $supplier->totalDebt();
    }

    /**
     * The supplier's purchase invoices that have a remaining, from the oldest date to the newest. Deleted invoices are not included.
     *
     * @return Collection<int, Invoice>
     */
    private function openInvoices(Supplier $supplier): Collection
    {
        return Invoice::query()
            ->debts()
            ->where('supplier_id', $supplier->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }
}
