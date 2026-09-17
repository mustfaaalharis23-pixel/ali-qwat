<?php

namespace App\Policies;

/**
 * The invoice printing page is a page that is only viewed: the only permission is invoice_printing.view.
 */
class InvoicePrintingPolicy extends ModelPolicy
{
    /**
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view'];
    }
}
