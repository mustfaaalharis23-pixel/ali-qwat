<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/** The same category can be used by items and purchase invoices. */
abstract class Category extends BaseModel
{
    use SoftDeletes;

    protected $table = 'invoice_categories';

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::deleting(function (Category $category): void {
            if ($category->items()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'items' => __('filament/resources/category.validation.has_items'),
                ]);
            }

            if ($category->invoices()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'invoices' => __('invoices.validation.category_has_invoices'),
                ]);
            }
        });
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_category_id');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'invoice_category_id');
    }
}
