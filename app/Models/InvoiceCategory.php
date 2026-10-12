<?php

namespace App\Models;

use Database\Factories\InvoiceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * The category of a purchase invoice, such as frames, lenses, medical glasses, or contact lenses.
 * The list is kept by the users, so a new category is added any time.
 */
#[Fillable(['name'])]
class InvoiceCategory extends BaseModel
{
    /** @use HasFactory<InvoiceCategoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A category that still has invoices cannot be deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (InvoiceCategory $category): void {
            $category->ensureHasNoInvoices();
        });
    }

    /**
     * The purchase invoices of this category.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @throws ValidationException
     */
    protected function ensureHasNoInvoices(): void
    {
        if ($this->invoices()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'invoices' => __('invoices.validation.category_has_invoices'),
            ]);
        }
    }
}
