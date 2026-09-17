<?php

namespace App\Policies;

/**
 * Orders can only be viewed: the only permission is order.view. Adding, editing, deleting, and restoring are for super users only.
 */
class OrderPolicy extends ModelPolicy
{
    /**
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view'];
    }
}
