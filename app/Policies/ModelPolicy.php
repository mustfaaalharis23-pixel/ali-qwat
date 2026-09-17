<?php

namespace App\Policies;

use App\Models\User;
use App\Permissions\PermissionName;
use Illuminate\Database\Eloquent\Model;

abstract class ModelPolicy
{
    /**
     * The basic abilities this Policy gives as permissions. A Policy can override it to give fewer, such as view only.
     *
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view', 'create', 'update', 'delete', 'restore'];
    }

    public function viewAny(User $user): bool
    {
        return $this->hasBasicPermission($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->hasBasicPermission($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->hasBasicPermission($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->hasBasicPermission($user, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->hasBasicPermission($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasBasicPermission($user, 'delete');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->hasBasicPermission($user, 'restore');
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasBasicPermission($user, 'restore');
    }

    /**
     * Force delete is never given as a permission; only a super user passes, through Gate::before.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Force delete is never given as a permission; only a super user passes, through Gate::before.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Whether the user has the permission for a basic ability. An ability this Policy does not give is always denied.
     */
    protected function hasBasicPermission(User $user, string $ability): bool
    {
        return in_array($ability, static::basicAbilities(), true) && $this->hasPermission($user, $ability);
    }

    /**
     * Whether the user has this Policy's permission for the ability, such as sales_invoice.mark_as_paid.
     */
    protected function hasPermission(User $user, string $ability): bool
    {
        return $user->hasPermission(PermissionName::for(static::class, $ability));
    }
}
