<?php

namespace App\Http\Controllers\Admin\Purchases;

use App\Http\Controllers\Admin\CategoryController;
use App\Models\InvoiceCategory;

class InvoiceCategoryController extends CategoryController
{
    protected const string MODEL = InvoiceCategory::class;
}
