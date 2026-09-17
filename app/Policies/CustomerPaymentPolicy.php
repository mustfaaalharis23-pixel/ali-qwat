<?php

namespace App\Policies;

class CustomerPaymentPolicy extends ModelPolicy
{
    /**
     * A payment is viewed, created, and deleted; it is never edited, and a deleted payment is not restored.
     *
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view', 'create', 'delete'];
    }
}
