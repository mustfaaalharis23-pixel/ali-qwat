<?php

namespace App\Permissions;

use Illuminate\Support\Str;

class PermissionName
{
    /**
     * Build a permission name from a Policy class and an ability: SalesInvoicePolicy with markAsPaid becomes sales_invoice.mark_as_paid.
     */
    public static function for(string $policy, string $ability): string
    {
        $model = Str::beforeLast(class_basename($policy), 'Policy');

        return Str::snake($model).'.'.Str::snake($ability);
    }
}
