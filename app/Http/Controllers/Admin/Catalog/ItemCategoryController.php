<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Admin\CategoryController;
use App\Models\ItemCategory;

class ItemCategoryController extends CategoryController
{
    protected const string MODEL = ItemCategory::class;
}
