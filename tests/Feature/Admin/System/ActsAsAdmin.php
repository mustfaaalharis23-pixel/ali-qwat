<?php

namespace Tests\Feature\Admin\System;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Signs in an active user with the given permissions, or a super user.
 */
trait ActsAsAdmin
{
    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true])->save();

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);
        }

        return $user;
    }

    private function superUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true, 'is_super' => true])->save();

        return $user;
    }
}
