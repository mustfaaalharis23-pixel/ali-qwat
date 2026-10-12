<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'image', 'description', 'is_active', 'supplier_id', 'sale_price'])]
class Item extends BaseModel
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The image file is removed from the public disk when it is replaced or when the item is force deleted.
     * A soft deleted item keeps its file, so it can be restored.
     *
     * Every saved item has a specific description under the purchases description with the item's name,
     * and force deleting the item force deletes that description first.
     */
    protected static function booted(): void
    {
        static::saved(function (Item $item): void {
            $item->syncSpecificDescription();
        });

        static::updated(function (Item $item): void {
            if ($item->wasChanged('image')) {
                Storage::disk('public')->delete($item->getRawOriginal('image'));
            }
        });

        static::forceDeleting(function (Item $item): void {
            $item->specificDescription()->withTrashed()->first()?->forceDeleteQuietly();
        });

        static::forceDeleted(function (Item $item): void {
            Storage::disk('public')->delete($item->image);
        });
    }

    /**
     * The specific description of this item, under the purchases description.
     *
     * @return HasOne<SpecificDescription, $this>
     */
    public function specificDescription(): HasOne
    {
        return $this->hasOne(SpecificDescription::class);
    }

    /**
     * The stock batches of this item, one for every purchase line it came in on.
     *
     * @return HasMany<Batch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * The sale lines of this item, one for every time it was sold on an order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The supplier (company) of this item. An item can have no supplier.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The active items. An inactive item is hidden across the system, so every list or count of items for work uses this.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * What the stock is worth now: for every active item that still has a quantity, its available quantity times
     * what one unit of it last cost, all added up. An inactive item is out of the system everywhere, so it is not counted,
     * and an item without a quantity adds nothing. This is the only definition of the value of the stock.
     */
    public static function stockValue(): float
    {
        return (float) static::query()
            ->active()
            ->where('available_quantity', '>', 0)
            ->sum(DB::raw('available_quantity * cost_price'));
    }

    /**
     * What the stock was worth at the end of that day, worked out again from the beginning: for every active item,
     * what it still had then times what one unit of it cost then, all added up.
     * What it had then is what came in on the purchase invoices both sides confirmed and dated up to that day,
     * minus what was sold on the orders dated up to it. What it cost then is the unit price of the last such purchase.
     * Nothing is read from the item's own columns, because those hold today's numbers only.
     * An item that had no quantity then adds nothing, exactly like the value of the stock now.
     * This is the only definition of the value of the stock at a moment.
     */
    public static function stockValueAt(CarbonImmutable $moment): float
    {
        $purchases = Batch::query()
            ->whereHas('invoice', fn (Builder $query): Builder => $query
                ->where('status', InvoiceStatus::Confirmed)
                ->where('date', '<=', $moment))
            ->orderBy('invoice_id')
            ->orderBy('id')
            ->get(['item_id', 'quantity', 'purchase_price']);

        $boughtQuantities = [];
        $costPrices = [];

        foreach ($purchases as $batch) {
            $boughtQuantities[$batch->item_id] = ($boughtQuantities[$batch->item_id] ?? 0.0) + (float) $batch->quantity;

            if ((float) $batch->quantity > 0 && (float) $batch->purchase_price > 0) {
                $costPrices[$batch->item_id] = round((float) $batch->purchase_price, 2);
            }
        }

        $soldQuantities = OrderItem::query()
            ->whereHas('order', fn (Builder $query): Builder => $query->where('date', '<=', $moment))
            ->toBase()
            ->selectRaw('item_id, sum(quantity) as quantity')
            ->groupBy('item_id')
            ->pluck('quantity', 'item_id');

        return (float) static::query()
            ->active()
            ->pluck('id')
            ->sum(function (int $itemId) use ($boughtQuantities, $soldQuantities, $costPrices): float {
                $quantity = ($boughtQuantities[$itemId] ?? 0.0) - (float) ($soldQuantities[$itemId] ?? 0.0);

                return $quantity > 0 ? $quantity * ($costPrices[$itemId] ?? 0.0) : 0.0;
            });
    }

    /**
     * The available quantity: what came in on the purchase invoices both sides confirmed, minus everything sold.
     * Nothing else enters it — a quantity that only the importer confirmed is not in the stock and is never counted here.
     * This is the only definition of what the factory has.
     */
    public function availableQuantity(): float
    {
        $bought = (float) $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)->sum('quantity');
        $sold = (float) OrderItem::query()
            ->where('item_id', $this->id)
            ->whereHas('order')
            ->sum('quantity');

        return $bought - $sold;
    }

    /**
     * The incoming quantity: what was bought and did not arrive yet, meaning the importer confirmed its invoice
     * and the receiver did not. It is information for the data entry only: it has no relation whatsoever to the
     * available quantity or to selling, and it becomes available only when both sides confirm the invoice.
     */
    public function incomingQuantity(): float
    {
        return (float) $this->batchesOfInvoicesWith(InvoiceStatus::ImporterConfirmed)->sum('quantity');
    }

    /**
     * Writes the available quantity again, from the beginning. Every change on the orders calls this, and nothing else:
     * it reads all the sales and all the purchases confirmed by both sides, and writes the number.
     * Nothing in the system adds to the number or takes from it, so it never drifts.
     */
    public function recalculateAvailableQuantity(): void
    {
        $this->available_quantity = $this->availableQuantity();

        $this->saveQuietly();
    }

    /**
     * Writes the available quantity, the incoming quantity, and the cost again, from the beginning.
     * Every change on the purchase side calls this, because a purchase can touch the three of them.
     */
    public function recalculateStock(): void
    {
        $costPrice = $this->costPriceOfTheLastPurchase();

        if (abs($costPrice - (float) $this->cost_price) > 0.0001) {
            $this->last_cost_price = (float) $this->cost_price;
        }

        $this->available_quantity = $this->availableQuantity();
        $this->incoming_quantity = $this->incomingQuantity();
        $this->cost_price = $costPrice;

        $this->saveQuietly();
    }

    /**
     * Everything sold of this item: the quantity of its lines on the orders that are not deleted.
     */
    public function soldQuantity(): float
    {
        return (float) OrderItem::query()
            ->where('item_id', $this->id)
            ->whereHas('order')
            ->sum('quantity');
    }

    /**
     * What one unit of this item cost on average across everything bought of it: all the money paid for it
     * over all the quantity bought, so a big purchase weighs more than a small one.
     * Only the purchases both sides confirmed are counted, like everywhere else.
     */
    public function averagePurchasePrice(): float
    {
        $batches = $this->purchases()->get();
        $quantity = (float) $batches->sum('quantity');

        if ($quantity <= 0) {
            return 0.0;
        }

        return round($batches->sum(fn (Batch $batch): float => (float) $batch->quantity * (float) $batch->purchase_price) / $quantity, 2);
    }

    /**
     * The most this item was ever bought for, per unit.
     */
    public function highestPurchasePrice(): float
    {
        return round((float) $this->purchases()->max('purchase_price'), 2);
    }

    /**
     * The least this item was ever bought for, per unit.
     */
    public function lowestPurchasePrice(): float
    {
        return round((float) $this->purchases()->min('purchase_price'), 2);
    }

    /**
     * The purchases of this item that count: the batches of the invoices both sides confirmed, of a real quantity and price.
     *
     * @return Builder<Batch>
     */
    protected function purchases(): Builder
    {
        return $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)
            ->where('quantity', '>', 0)
            ->where('purchase_price', '>', 0);
    }

    /**
     * The unit price of the last batch that came in on an invoice both sides confirmed, rounded to two decimals.
     * A batch without a quantity or without a price, such as a return, does not set the cost. Without any batch the cost is zero.
     */
    protected function costPriceOfTheLastPurchase(): float
    {
        $batch = $this->purchases()
            ->orderByDesc('invoice_id')
            ->orderByDesc('id')
            ->first();

        return $batch === null ? 0.0 : round((float) $batch->purchase_price, 2);
    }

    /**
     * This item's batches that came in on invoices of the given status. Deleted batches and deleted invoices are left out.
     *
     * @return Builder<Batch>
     */
    protected function batchesOfInvoicesWith(InvoiceStatus $status): Builder
    {
        return Batch::query()
            ->where('item_id', $this->id)
            ->whereHas('invoice', fn (Builder $query): Builder => $query->where('status', $status));
    }

    /**
     * The profit margin: the sale price minus the cost price. It is not stored.
     * The cost price is read only for users: it is not fillable, so only the system sets it.
     */
    public function profitMargin(): float
    {
        return (float) $this->sale_price - (float) $this->cost_price;
    }

    /**
     * Creates the item's specific description, or renames it to the item's name.
     * It is saved without an audit, because it is a copy of the item.
     */
    protected function syncSpecificDescription(): void
    {
        $description = $this->specificDescription()->withTrashed()->first();

        if ($description === null) {
            (new SpecificDescription([
                'general_description_id' => GeneralDescription::purchases()->id,
                'item_id' => $this->id,
                'name' => $this->name,
            ]))->saveQuietly();

            return;
        }

        if ($description->name !== $this->name) {
            $description->name = $this->name;
            $description->saveQuietly();
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
            'is_active' => 'boolean',
            'sale_price' => 'float',
            'cost_price' => 'float',
            'last_cost_price' => 'float',
            'available_quantity' => 'float',
            'incoming_quantity' => 'float',
        ];
    }
}
