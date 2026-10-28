<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Orders are viewed and edited: the permissions are order.view and order.update. Adding, deleting, and restoring are for super users only.
 */
class OrderPolicy extends ModelPolicy
{
    /**
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view', 'update'];
    }

    /**
     * A deleted order is not edited.
     *
     * @param  Order  $model
     */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $model->trashed();
    }
}
