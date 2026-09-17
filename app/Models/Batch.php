<?php

namespace App\Models;

use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The stock a purchase line brings in: its quantity at its unit price.
 * A batch is never created or changed by hand; it is a copy of its line, so it is written with the line.
 */
#[Fillable([
    'invoice_id',
    'invoice_item_id',
    'item_id',
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
