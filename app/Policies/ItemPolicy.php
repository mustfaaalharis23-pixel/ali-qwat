<?php

namespace App\Policies;

use App\Models\User;

class ItemPolicy extends ModelPolicy
{
    /**
     * The cost price of an item, and everything it can be worked out from, is its own permission (item.view_cost_price):
     * a user who can see the items does not see what they cost us unless this permission is given to them.
     */
    public function viewCostPrice(User $user): bool
    {
        return $this->hasPermission($user, __FUNCTION__);
    }
}
