<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name_ar', 'name_zh', 'phone', 'email', 'contract', 'notes'])]
class Supplier extends BaseModel
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The contract file is removed from the public disk when it is replaced or removed, or when the supplier is force deleted.
     * A soft deleted supplier keeps its file, so it can be restored.
     */
    protected static function booted(): void
    {
        static::updated(function (Supplier $supplier): void {
            if ($supplier->wasChanged('contract') && filled($supplier->getRawOriginal('contract'))) {
                Storage::disk('public')->delete($supplier->getRawOriginal('contract'));
            }
        });

        static::forceDeleted(function (Supplier $supplier): void {
            if (filled($supplier->contract)) {
                Storage::disk('public')->delete($supplier->contract);
            }
        });
    }

    /**
     * The items (products) of this supplier.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * The invoices of this supplier. Only purchase invoices have a supplier.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The total purchases from this supplier: the sum of the amounts of its purchase invoices.
     */
    public function totalPurchases(): float
    {
        return (float) $this->invoices()->purchases()->sum('amount');
    }

    /**
     * The total paid to this supplier: the sum of the paid amounts of its purchase invoices, which are the sums of their payoffs.
     */
    public function totalPaid(): float
    {
        return (float) $this->invoices()->purchases()->sum('paid_amount');
    }

    /**
     * The total debt to this supplier: the sum of the remaining of its debts.
     */
    public function totalDebt(): float
    {
        return (float) $this->invoices()->debts()->sum('remaining');
    }

    /**
     * The number of this supplier's items that are not deleted.
     */
    public function itemsCount(): int
    {
        return $this->items()->count();
    }
}
