<?php

namespace App\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ships part of a purchase invoice in a container by splitting it in two.
 * The chosen cartons of each line become a new invoice in the container, and the original keeps what is left.
 * The payoffs stay on the original up to its new amount, and the rest moves to the new part, newest payoff first,
 * so the supplier's debt and the incoming stock do not change.
 */
class SplitPurchaseInvoice
{
    /**
     * @param  array<int, int>  $cartonsByLine  the cartons that go, keyed by the line ID; a line that is not given sends none.
     *
     * @throws ValidationException
     */
    public function handle(Invoice $invoice, Container $container, array $cartonsByLine): Invoice
    {
        $this->ensureTheInvoiceCanBeSplit($invoice);

        /** @var Collection<int, InvoiceItem> $lines */
        $lines = $invoice->items()->get()->keyBy('id');

        $this->ensureTheCartonsFitTheLines($lines, $cartonsByLine);

        return DB::transaction(function () use ($invoice, $container, $lines, $cartonsByLine): Invoice {
            $part = $this->createThePart($invoice, $container);

            /** @var array<int, array{packets: float, price: float}> $shipped */
            $shipped = [];

            foreach ($lines as $line) {
                $cartons = (int) ($cartonsByLine[$line->id] ?? 0);

                if ($cartons === 0) {
                    continue;
                }

                $packets = $this->packetsIn($line, $cartons);
                $price = round($line->price * $packets / $line->boxes_count, 2);
                $shipped[$line->id] = ['packets' => $packets, 'price' => $price];

                $part->items()->create([
                    'specific_description_id' => $line->specific_description_id,
                    'boxes_count' => $packets,
                    'box_quantity' => $line->box_quantity,
                    'boxes_per_carton' => $line->boxes_per_carton,
                    'carton_cbm' => $line->carton_cbm,
                    'produced_at' => $line->produced_at,
                    'price' => $price,
                    'unit_cost' => $line->unit_cost,
                ]);
            }

            $invoice->refresh();
            $this->movePayoffsOverTheRest($invoice, $part, (float) $invoice->amount - array_sum(array_column($shipped, 'price')));

            foreach ($shipped as $lineId => ['packets' => $packets, 'price' => $price]) {
                $line = $lines[$lineId];

                if ($packets >= $line->boxes_count) {
                    $line->delete();

                    continue;
                }

                $line->update([
                    'boxes_count' => $line->boxes_count - $packets,
                    'price' => $line->price - $price,
                ]);
            }

            return $part;
        });
    }

    /**
     * The new invoice of the shipped part, in the container. It copies the original's supplier, date, rates, price of one CBM, status, confirmation,
     * stage, and images, is linked to the original, and is named after it with its part number.
     * The name and stage are set after creating it, because a new purchase invoice is named after its supplier and starts in production.
     */
    protected function createThePart(Invoice $invoice, Container $container): Invoice
    {
        $number = $invoice->splitParts()->withTrashed()->count() + 1;

        $part = Invoice::query()->create([
            'name' => $invoice->name,
            'type' => $invoice->type,
            'general_description_id' => $invoice->general_description_id,
            'supplier_id' => $invoice->supplier_id,
            'invoice_category_id' => $invoice->invoice_category_id,
            'user_id' => $invoice->user_id,
            'date' => $invoice->date,
            'exchange_rate' => $invoice->exchange_rate,
            'exchange_rate_source' => $invoice->exchange_rate_source,
            'dinar_rate' => $invoice->dinar_rate,
            'cbm_price' => $invoice->cbm_price,
            'status' => $invoice->status,
            'images' => $invoice->images,
        ]);

        $part->forceFill([
            'name' => __('invoices.split_part_name', ['name' => $invoice->name, 'number' => $number]),
            'stage' => $invoice->stage,
            'container_id' => $container->id,
            'split_from_invoice_id' => $invoice->id,
            'importer_checker_id' => $invoice->importer_checker_id,
            'importer_confirmed_at' => $invoice->importer_confirmed_at,
        ])->save();

        return $part;
    }

    /**
     * Moves the payoffs the original cannot keep to the new part: the original keeps up to what it is left with,
     * and the rest moves, newest payoff first, splitting a payoff when only part of it has to move.
     */
    protected function movePayoffsOverTheRest(Invoice $invoice, Invoice $part, float $amountLeft): void
    {
        $toMove = (float) $invoice->paid_amount - $amountLeft;

        $payoffs = $invoice->payoffs()->orderByDesc('date')->orderByDesc('id')->get();

        foreach ($payoffs as $payoff) {
            if ($toMove < 0.0001) {
                return;
            }

            $amount = min((float) $payoff->amount, $toMove);
            $payoff->moveToSplitInvoice($part, $amount);
            $toMove -= $amount;
        }
    }

    /**
     * The packets in the given cartons of a line: every carton is full, except that all the line's cartons carry all its packets,
     * because its last carton may be only partly filled. The split window uses it too, to show what goes before the split is made.
     */
    public function packetsIn(InvoiceItem $line, int $cartons): float
    {
        if ($cartons >= $line->cartons_count) {
            return (float) $line->boxes_count;
        }

        return $cartons * (float) $line->boxes_per_carton;
    }

    /**
     * Only a purchase invoice that is not deleted, not confirmed (its goods are not in the warehouse yet),
     * and not already in a container is split.
     *
     * @throws ValidationException
     */
    protected function ensureTheInvoiceCanBeSplit(Invoice $invoice): void
    {
        if (! $invoice->isPurchase()
            || $invoice->trashed()
            || $invoice->status === InvoiceStatus::Confirmed
            || $invoice->container_id !== null) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.split_needs_an_open_purchase_invoice'),
            ]);
        }
    }

    /**
     * Every given line is on the invoice, with between zero and its own cartons.
     * Some cartons must go, but not all the cartons of every line: that is the whole invoice, which is added as it is.
     *
     * @param  Collection<int, InvoiceItem>  $lines
     * @param  array<int, int>  $cartonsByLine
     *
     * @throws ValidationException
     */
    protected function ensureTheCartonsFitTheLines(Collection $lines, array $cartonsByLine): void
    {
        foreach ($cartonsByLine as $lineId => $cartons) {
            $line = $lines->get($lineId);

            if ($line === null) {
                throw ValidationException::withMessages([
                    'cartons' => __('invoices.validation.split_line_not_on_the_invoice'),
                ]);
            }

            if ($cartons < 0 || $cartons > (int) $line->cartons_count) {
                throw ValidationException::withMessages([
                    'cartons' => __('invoices.validation.split_cartons_out_of_range'),
                ]);
            }
        }

        if (array_sum($cartonsByLine) === 0) {
            throw ValidationException::withMessages([
                'cartons' => __('invoices.validation.split_needs_some_cartons'),
            ]);
        }

        $takesEveryCarton = $lines->every(fn (InvoiceItem $line): bool => ($cartonsByLine[$line->id] ?? 0) >= (int) $line->cartons_count);

        if ($takesEveryCarton) {
            throw ValidationException::withMessages([
                'cartons' => __('invoices.validation.split_cannot_take_the_whole_invoice'),
            ]);
        }
    }
}
