<?php

namespace App\Models;

use Database\Factories\ItemCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/** Preserves the category permissions for this section of the application. */
class ItemCategory extends Category
{
    /** @use HasFactory<ItemCategoryFactory> */
    use HasFactory;
}
