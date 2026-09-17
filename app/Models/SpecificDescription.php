<?php

namespace App\Models;

use App\Enums\OrderPaymentType;
use Database\Factories\SpecificDescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['general_description_id', 'item_id', 'name', 'transfer_type'])]
class SpecificDescription extends BaseModel
{
    /** @use HasFactory<SpecificDescriptionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The description that money of the payment type is moved under: the first description with that transfer type,
     * or null when there is none. This is the only way a transfer finds its description.
     */
    public static function forTransfer(OrderPaymentType $paymentType): ?self
    {
        return static::query()
            ->where('transfer_type', $paymentType)
            ->oldest('id')
            ->first();
    }

    /**
     * The general description this specific description is under.
     *
     * @return BelongsTo<GeneralDescription, $this>
     */
    public function generalDescription(): BelongsTo
    {
        return $this->belongsTo(GeneralDescription::class);
    }

    /**
     * The item of this description, only for the descriptions of items under the purchases description.
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
            'transfer_type' => OrderPaymentType::class,
        ];
    }
}
