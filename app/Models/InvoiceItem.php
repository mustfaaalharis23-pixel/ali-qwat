<?php

namespace App\Models;

use App\Enums\OrderPaymentType;
use App\Metrics\Period;
use App\Observers\StockObserver;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[ObservedBy(StockObserver::class)]
#[Fillable([
    'invoice_id',
    'specific_description_id',
    'price',
    'unit_cost',
    'quantity',
    'boxes_count',
    'box_quantity',
    'boxes_per_carton',
    'cartons_count',
    'carton_cbm',
    'produced_at',
])]
class InvoiceItem extends BaseModel
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Every line follows its invoice's rules before it is saved, wherever it is saved from.
     * A purchase line cannot be deleted when that would leave the invoice's amount below its paid amount.
     * Saving or deleting a line recalculates its invoice's amounts, and writes or deletes its batch with it.
     */
    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $line): void {
            $line->applyInvoiceRules();
        });

        static::deleting(function (InvoiceItem $line): void {
            $invoice = Invoice::withTrashed()->find($line->invoice_id);

            if ($invoice?->isPurchase()) {
                $line->ensureAmountCoversThePaidAmount($invoice, 0);
            }
        });

        static::saved(function (InvoiceItem $line): void {
            Invoice::withTrashed()->find($line->invoice_id)?->recalculateAmounts();
            $line->syncBatch();
        });

        static::forceDeleting(function (InvoiceItem $line): void {
            $line->batch()->withTrashed()->first()?->forceDeleteQuietly();
        });

        static::deleted(function (InvoiceItem $line): void {
            Invoice::withTrashed()->find($line->invoice_id)?->recalculateAmounts();

            if (! $line->isForceDeleting()) {
                $line->batch()->first()?->deleteQuietly();
            }
        });
    }

    /**
     * The money withdrawn from the box of the payment type inside the period: the sum of the prices of the lines
     * whose specific description has this payment type as its transfer type, even when the description is deleted,
     * only of the invoices of the given cashier when one is given.
     * The period is on the invoice date. Deleted lines and the lines of deleted invoices are not counted.
     * This is the only definition of the money withdrawn from a box.
     */
    public static function withdrawn(OrderPaymentType $paymentType, Period $period, ?User $cashier = null): float
    {
        return (float) static::query()
            ->whereHas('specificDescription', fn (Builder $query): Builder => $query
                ->withTrashed()
                ->where('transfer_type', $paymentType))
            ->whereHas('invoice', function (Builder $query) use ($period, $cashier): void {
                $period->applyTo($query, 'date')
                    ->when($cashier, fn (Builder $query): Builder => $query->where('cashier_id', $cashier->id));
            })
            ->sum('price');
    }

    /**
     * The invoice of this line.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The specific description of this line.
     *
     * @return BelongsTo<SpecificDescription, $this>
     */
    public function specificDescription(): BelongsTo
    {
        return $this->belongsTo(SpecificDescription::class);
    }

    /**
     * The item of this line, taken from its specific description.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The stock batch of this line. A general invoice line has none.
     *
     * @return HasOne<Batch, $this>
     */
    public function batch(): HasOne
    {
        return $this->hasOne(Batch::class);
    }

    /**
     * The description must be under the invoice's general description, and the line takes the description's item.
     * A general invoice line is one unit in one box that takes no space. A purchase line needs an item description,
     * its quantity is packets (boxes) × packet quantity, its cartons are its packets ÷ the packets one carton holds,
     * with a partly filled carton counted as a whole one, and its CBM is cartons × the CBM of one carton.
     *
     * @throws ValidationException
     */
    protected function applyInvoiceRules(): void
    {
        $invoice = Invoice::withTrashed()->findOrFail($this->invoice_id);
        $description = SpecificDescription::withTrashed()->findOrFail($this->specific_description_id);

        if ((int) $description->general_description_id !== (int) $invoice->general_description_id) {
            throw ValidationException::withMessages([
                'specific_description_id' => __('invoices.validation.description_not_under_invoice'),
            ]);
        }

        $this->item_id = $description->item_id;

        if (! $invoice->isPurchase()) {
            $this->quantity = 1;
            $this->boxes_count = 1;
            $this->box_quantity = 1;
            $this->boxes_per_carton = null;
            $this->cartons_count = null;
            $this->carton_cbm = null;
            $this->cbm = 0;
            $this->unit_cost = null;
            $this->unit_cost_usd = null;
            $this->price_usd = null;
            $this->transport_cost_usd = null;
            $this->transport_cost_per_piece_usd = null;
            $this->unit_cost_iqd = null;
            $this->price_iqd = null;
            $this->transport_cost_iqd = null;
            $this->transport_cost_per_piece_iqd = null;
            $this->final_unit_cost_iqd = null;

            return;
        }

        if ($description->item_id === null) {
            throw ValidationException::withMessages([
                'specific_description_id' => __('invoices.validation.purchase_line_needs_item'),
            ]);
        }

        if ($this->boxes_count !== null && $this->box_quantity !== null) {
            $this->quantity = $this->boxes_count * $this->box_quantity;
        }

        $this->cartons_count = $this->boxes_count !== null && $this->boxes_per_carton > 0
            ? ceil($this->boxes_count / $this->boxes_per_carton)
            : null;

        $this->cbm = $this->cartons_count !== null && $this->carton_cbm !== null ? $this->cartons_count * $this->carton_cbm : 0;

        $this->applyCosts($invoice);
        $this->ensureAmountCoversThePaidAmount($invoice, (float) $this->price);
    }

    /**
     * The purchase line's saved costs, for building the item's final cost:
     * the unit cost in yuan as typed (its price ÷ its pieces when it was not given), the unit cost and the price in US dollars
     * through the invoice's exchange rate (none without a rate), and the transport cost in US dollars
     * (its CBM × the invoice's price of one CBM, none without that price) and the transport of one piece.
     * Each dollar cost is also saved in Iraqi dinars through the invoice's dinar rate (none without it), and the final cost
     * of one piece in dinars is its unit cost plus its transport.
     */
    protected function applyCosts(Invoice $invoice): void
    {
        if ($this->unit_cost === null && $this->quantity > 0) {
            $this->unit_cost = $this->price / $this->quantity;
        }

        $rate = (float) $invoice->exchange_rate;
        $this->unit_cost_usd = $rate > 0 && $this->unit_cost !== null ? $this->unit_cost / $rate : null;
        $this->price_usd = $rate > 0 ? $this->price / $rate : null;

        $this->transport_cost_usd = $invoice->cbm_price !== null ? (float) $this->cbm * $invoice->cbm_price : null;
        $this->transport_cost_per_piece_usd = $this->transport_cost_usd !== null && $this->quantity > 0
            ? $this->transport_cost_usd / $this->quantity
            : null;

        $this->applyDinarCosts($invoice->dinarsPerDollar());
    }

    /**
     * The dollar costs of the line in Iraqi dinars, at the given dinars in one dollar. None without the rate or without the costs in dollars.
     * The final cost of one piece is its unit cost plus the transport of one piece, all in dinars.
     */
    protected function applyDinarCosts(?float $dinarsPerDollar): void
    {
        $inDinars = fn (?float $dollars): ?float => $dinarsPerDollar !== null && $dollars !== null ? $dollars * $dinarsPerDollar : null;

        $this->unit_cost_iqd = $inDinars($this->unit_cost_usd);
        $this->price_iqd = $inDinars($this->price_usd);
        $this->transport_cost_iqd = $inDinars($this->transport_cost_usd);
        $this->transport_cost_per_piece_iqd = $inDinars($this->transport_cost_per_piece_usd);
        $this->final_unit_cost_iqd = $this->unit_cost_iqd !== null ? $this->unit_cost_iqd + ($this->transport_cost_per_piece_iqd ?? 0) : null;
    }

    /**
     * A purchase line has one batch, which is written with it: the same quantity, and the unit price the line was bought at:
     * the final cost of one piece in Iraqi dinars, or, for a line without one, its price over its quantity, both without their sign.
     * The batch is a copy of the line, so it is saved without an audit. A general invoice line gets no batch.
     */
    protected function syncBatch(): void
    {
        if (! Invoice::withTrashed()->find($this->invoice_id)?->isPurchase()) {
            return;
        }

        $quantity = (float) $this->quantity;
        $batch = $this->batch()->withTrashed()->first() ?? new Batch;

        $batch->fill([
            'invoice_id' => $this->invoice_id,
            'invoice_item_id' => $this->id,
            'item_id' => $this->item_id,
            'quantity' => $quantity,
            'purchase_price' => match (true) {
                $this->final_unit_cost_iqd !== null => abs($this->final_unit_cost_iqd),
                $quantity == 0.0 => 0.0,
                default => abs((float) $this->price) / abs($quantity),
            },
        ])->saveQuietly();
    }

    /**
     * A purchase invoice's remaining is never negative: its other lines plus this line's price must cover its payoffs.
     *
     * @throws ValidationException
     */
    protected function ensureAmountCoversThePaidAmount(Invoice $invoice, float $linePrice): void
    {
        $otherLines = (float) $invoice->items()
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->sum('price');

        $paidAmount = (float) $invoice->payoffs()->sum('amount');

        if ($otherLines + $linePrice < $paidAmount - 0.0001) {
            throw ValidationException::withMessages([
                'price' => __('invoices.validation.amount_below_paid_amount'),
            ]);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'unit_cost' => 'float',
            'unit_cost_usd' => 'float',
            'price_usd' => 'float',
            'unit_cost_iqd' => 'float',
            'price_iqd' => 'float',
            'transport_cost_iqd' => 'float',
            'transport_cost_per_piece_iqd' => 'float',
            'final_unit_cost_iqd' => 'float',
            'transport_cost_usd' => 'float',
            'transport_cost_per_piece_usd' => 'float',
            'quantity' => 'float',
            'boxes_count' => 'float',
            'box_quantity' => 'float',
            'boxes_per_carton' => 'float',
            'cartons_count' => 'float',
            'carton_cbm' => 'float',
            'cbm' => 'float',
            'produced_at' => 'date',
            'profit_difference' => 'float',
        ];
    }
}
