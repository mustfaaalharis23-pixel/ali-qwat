<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Policies\ModelPolicy;

/**
 * EN: A fake Policy used only by tests. It gets the five basic permissions from ModelPolicy, adds one custom permission, and has a helper method that is not a permission.
 * AR: Policy وهمي بس للـ tests. ياخذ الصلاحيات الخمسة من ModelPolicy، ويضيف صلاحية خاصة وحدة، وبيه method مساعدة مو صلاحية.
 */
class SalesInvoicePolicy extends ModelPolicy
{
    /**
     * EN: Custom permission: checks sales_invoice.mark_as_paid.
     * AR: صلاحية خاصة: تفحص sales_invoice.mark_as_paid.
     */
    public function markAsPaid(User $user, SalesInvoice $invoice): bool
    {
        return ! $this->isLocked($invoice) && $this->hasPermission($user, __FUNCTION__);
    }

    /**
     * EN: A helper method, not a permission, because it is protected.
     * AR: method مساعدة مو صلاحية، لأنها protected.
     */
    protected function isLocked(SalesInvoice $invoice): bool
    {
        return false;
    }
}
