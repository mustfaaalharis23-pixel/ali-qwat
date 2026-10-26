<?php

namespace App\Models;

use App\Observers\StockObserver;
use Database\Factories\InvoiceItemGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * Some of the packets of a purchase line at one lens power (or without one): a line of 10 packets can be 6 packets of 1.00
 * and 4 packets of 1.50. The colors in a packet are the line's, chosen once for all its packets. The packets of a line's groups
 * add up to the line's packets, and the line has a stock batch for each of its groups.
 */
#[ObservedBy(StockObserver::class)]
#[Fillable([
    'invoice_item_id',
    'lens_power_id',
    'boxes_count',
])]
class InvoiceItemGroup extends BaseModel
{
    /** @use HasFactory<InvoiceItemGroupFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The packets of a group are whole: there is no half a packet. Every change of a line's groups writes the line's batches again.
     */
    protected static function booted(): void
    {
        static::saving(function (InvoiceItemGroup $group): void {
            if (abs((float) $group->boxes_count - round((float) $group->boxes_count)) >= 0.0001) {
                throw ValidationException::withMessages([
                    'boxes_count' => __('invoices.validation.must_be_whole', ['attribute' => __('filament/resources/invoice_item.fields.boxes_count')]),
                ]);
            }
        });

        $syncTheBatches = function (InvoiceItemGroup $group): void {
            InvoiceItem::withTrashed()->find($group->invoice_item_id)?->syncBatches();
        };

        static::saved($syncTheBatches);
        static::deleted($syncTheBatches);
        static::restored($syncTheBatches);
    }

    /**
     * The purchase line these packets are on.
     *
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /**
     * The lens power of these packets, or none.
     *
     * @return BelongsTo<LensPower, $this>
     */
    public function lensPower(): BelongsTo
    {
        return $this->belongsTo(LensPower::class);
    }

    /**
     * The group as one short text for the screens: its power when it has one and its packets, such as "Power 1.00 · 6 packets".
     */
    public function description(): string
    {
        $number = fn (mixed $value): string => (string) Number::format((float) $value, maxPrecision: 2, locale: app()->getLocale());

        return collect([
            $this->lensPower === null ? null : __('filament/resources/invoice_item.group.power', ['value' => $this->lensPower->value]),
            __('filament/resources/invoice_item.group.packets', ['packets' => $number($this->boxes_count)]),
        ])->filter()->implode(' · ');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'boxes_count' => 'float',
        ];
    }
}
