<?php

namespace App\Models;

use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The stock one group of a purchase line brings in (or the whole line, when it has no groups): its quantity in pieces at the price of a piece,
 * its lens power when it has one, and the pieces of each of its colors when it has colors.
 * A batch is never created or changed by hand; it is a copy of its line, so it is written with the line.
 */
#[Fillable([
    'invoice_id',
    'invoice_item_id',
    'invoice_item_group_id',
    'item_id',
    'lens_power_id',
    'quantity',
    'purchase_price',
])]
class Batch extends BaseModel
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The invoice this batch came in with.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The line this batch was created from.
     *
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /**
     * The item this batch is stock of.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The group of the line this batch was created from. Null for a line without groups.
     *
     * @return BelongsTo<InvoiceItemGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(InvoiceItemGroup::class, 'invoice_item_group_id');
    }

    /**
     * The pieces of each color in this batch. A batch without colors has none.
     *
     * @return HasMany<BatchColor, $this>
     */
    public function colors(): HasMany
    {
        return $this->hasMany(BatchColor::class);
    }

    /**
     * Writes the pieces of each color of this batch from the beginning, keyed by the color, and removes for good a color it no longer has,
     * because they are only a copy of its group. They are saved without an audit, like the batch.
     *
     * @param  array<int, float>  $piecesByColor
     */
    public function syncColors(array $piecesByColor): void
    {
        $colors = $this->colors()->withTrashed()->get()->keyBy('color_id');

        foreach ($piecesByColor as $colorId => $pieces) {
            $color = $colors->pull($colorId) ?? new BatchColor(['batch_id' => $this->id, 'color_id' => $colorId]);
            $color->pieces = $pieces;

            $color->trashed() ? $color->restoreQuietly() : $color->saveQuietly();
        }

        $colors->each(fn (BatchColor $color): ?bool => $color->forceDeleteQuietly());
    }

    /**
     * Removes this batch for good, with the pieces of its colors.
     */
    public function forceDeleteWithColors(): void
    {
        $this->colors()->withTrashed()->get()->each(fn (BatchColor $color): ?bool => $color->forceDeleteQuietly());
        $this->forceDeleteQuietly();
    }

    /**
     * The lens power of this batch, the same as its group's. Null when it has none.
     *
     * @return BelongsTo<LensPower, $this>
     */
    public function lensPower(): BelongsTo
    {
        return $this->belongsTo(LensPower::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'purchase_price' => 'float',
        ];
    }
}
