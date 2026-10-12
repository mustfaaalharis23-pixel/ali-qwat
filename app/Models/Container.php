<?php

namespace App\Models;

use Database\Factories\ContainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * A shipping container. Its ID is its number in the system; the international number is the one written on the container.
 * Its price of one cubic meter (CBM), in US dollars, is a note only: each invoice in it is costed at its own price of one CBM.
 */
#[Fillable(['international_number', 'cbm_price', 'loading_date', 'expected_arrival_date', 'notes'])]
class Container extends BaseModel
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A container that still has invoices cannot be deleted; its invoices are taken out of it first.
     */
    protected static function booted(): void
    {
        static::deleting(function (Container $container): void {
            $container->ensureHasNoInvoices();
        });
    }

    /**
     * How the container is named on screens: its system number, then its international number when it has one, such as #3 MSCU1234566.
     */
    public function displayName(): string
    {
        return trim("#{$this->id} {$this->international_number}");
    }

    /**
     * The purchase invoices (shipments) in this container.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The CBM of the container: the sum of the CBM of its invoices. Deleted invoices are not counted.
     */
    public function totalCbm(): float
    {
        return (float) $this->invoices()->sum('cbm');
    }

    /**
     * The transport cost of the whole container in US dollars: the sum of its invoices' transport costs,
     * each at the invoice's own price of one CBM. Deleted invoices are not counted.
     */
    public function transportCost(): float
    {
        return (float) $this->invoices()->sum('transport_cost_usd');
    }

    /**
     * A force delete also counts the deleted invoices, because they still point to the container.
     *
     * @throws ValidationException
     */
    protected function ensureHasNoInvoices(): void
    {
        if ($this->invoices()->when($this->isForceDeleting(), fn ($query) => $query->withTrashed())->exists()) {
            throw ValidationException::withMessages([
                'container' => __('containers.validation.container_has_invoices'),
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
            'cbm_price' => 'float',
            'loading_date' => 'date',
            'expected_arrival_date' => 'date',
        ];
    }
}
