<?php

namespace App\Models;

use Database\Factories\ItemCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * The category of an item, such as frames, lenses, or cases. The list is kept by the users, so a new category is added any time,
 * and the items list is filtered by it.
 */
#[Fillable(['name'])]
class ItemCategory extends BaseModel
{
    /** @use HasFactory<ItemCategoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A category that still has items, deleted ones included, cannot be deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (ItemCategory $category): void {
            $category->ensureHasNoItems();
        });
    }

    /**
     * The items of this category.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @throws ValidationException
     */
    protected function ensureHasNoItems(): void
    {
        if ($this->items()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'items' => __('filament/resources/item_category.validation.has_items'),
            ]);
        }
    }
}
