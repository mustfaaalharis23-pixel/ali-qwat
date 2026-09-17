<?php

namespace App\Permissions;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SyncUserPermissions
{
    /**
     * Replace the user's permissions with the given ones. Only a super user can do this.
     *
     * @param  list<string>  $permissions
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user, array $permissions): void
    {
        if ($actor->is_super !== true) {
            throw new AuthorizationException;
        }

        $permissions = array_values(array_unique($permissions));

        DB::transaction(function () use ($user, $permissions): void {
            DB::table('user_permissions')
                ->where('user_id', $user->id)
                ->whereNotIn('permission', $permissions)
                ->delete();

            DB::table('user_permissions')->insertOrIgnore(array_map(
                fn (string $permission): array => ['user_id' => $user->id, 'permission' => $permission],
                $permissions,
            ));
        });
    }
}
