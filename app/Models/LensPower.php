<?php

namespace App\Models;

use Database\Factories\LensPowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * A lens power the purchased glasses come in, such as 1.50. The packets of a purchase line are bought in groups, each at a power
 * or without one, and the line has a stock batch for each of its groups. The list is kept by the users, so a new power is added any time.
 */
#[Fillable(['value'])]
class LensPower extends BaseModel
{
    /** @use HasFactory<LensPowerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A power that is on a purchase line cannot be deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (LensPower $power): void {
            $power->ensureIsOnNoLine();
        });
    }

    /**
     * The groups of the purchase lines' packets at this power.
     *
     * @return HasMany<InvoiceItemGroup, $this>
     */
    public function invoiceItemGroups(): HasMany
    {
        return $this->hasMany(InvoiceItemGroup::class);
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsOnNoLine(): void
    {
        if ($this->invoiceItemGroups()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'invoice_items' => __('invoices.validation.lens_power_on_lines'),
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
            'value' => 'decimal:2',
        ];
    }
}
