<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * The pieces of one color in each packet of a purchase line, chosen once for all its packets: a packet of 24 pieces can hold
 * 10 red pieces and 14 blue. The pieces of a line's colors fill its packet, and every stock batch of the line (one for each
 * of its powers) holds its packets × these pieces of the color.
 */
#[Fillable([
    'invoice_item_id',
    'color_id',
    'pieces',
])]
class InvoiceItemColor extends BaseModel
{
    use SoftDeletes;

    /**
     * The pieces are whole: there is no half a piece. Every change of a line's colors writes the line's batches again.
     */
    protected static function booted(): void
    {
        static::saving(function (InvoiceItemColor $color): void {
            if (abs((float) $color->pieces - round((float) $color->pieces)) >= 0.0001) {
                throw ValidationException::withMessages([
                    'pieces' => __('invoices.validation.must_be_whole', ['attribute' => __('filament/resources/invoice_item.fields.pieces')]),
                ]);
            }
        });

        $syncTheBatches = function (InvoiceItemColor $color): void {
            InvoiceItem::withTrashed()->find($color->invoice_item_id)?->syncBatches();
        };

        static::saved($syncTheBatches);
        static::deleted($syncTheBatches);
        static::restored($syncTheBatches);
    }

    /**
     * The purchase line whose packets hold this color.
     *
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /**
     * The color of these pieces.
     *
     * @return BelongsTo<Color, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pieces' => 'float',
        ];
    }
}
