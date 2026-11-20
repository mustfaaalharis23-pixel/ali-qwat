<?php

namespace App\Models;

use Database\Factories\InvoiceCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/** Preserves the category permissions for this section of the application. */
class InvoiceCategory extends Category
{
    /** @use HasFactory<InvoiceCategoryFactory> */
    use HasFactory;
}
