<?php

namespace Tests\Unit;

use App\Models\User;
use App\Permissions\SyncUserPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncUserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A super user saves the chosen permissions for a user.
     * AR: السوبر يحفظ الصلاحيات المختارة ليوزر.
     */
    public function test_chosen_permissions_are_saved(): void
    {
        $super = User::factory()->create(['is_super' => true]);
        $user = User::factory()->create();

        (new SyncUserPermissions)->handle($super, $user, ['sales_invoice.view', 'sales_invoice.update']);

        $this->assertSame(['sales_invoice.update', 'sales_invoice.view'], $this->permissionsOf($user));
    }

    /**
     * EN: Permissions that are no longer chosen are removed from the user.
     * AR: الصلاحيات اللي انشالت من الاختيار تنمسح من اليوزر.
     */
    public function test_permissions_no_longer_chosen_are_removed(): void
    {
        $super = User::factory()->create(['is_super' => true]);
        $user = User::factory()->create();
        (new SyncUserPermissions)->handle($super, $user, ['sales_invoice.view', 'sales_invoice.update']);

        (new SyncUserPermissions)->handle($super, $user, ['sales_invoice.view']);

        $this->assertSame(['sales_invoice.view'], $this->permissionsOf($user));
    }

    /**
     * EN: A normal user cannot give permissions, and nothing is saved.
     * AR: اليوزر العادي ما يكدر يعطي صلاحيات، وما ينحفظ شي.
     */
    public function test_only_a_super_user_can_give_permissions(): void
    {
        $normalUser = User::factory()->create();
        $user = User::factory()->create();

        try {
            (new SyncUserPermissions)->handle($normalUser, $user, ['sales_invoice.view']);
            $this->fail('A normal user was able to give permissions.');
        } catch (AuthorizationException) {
            $this->assertSame([], $this->permissionsOf($user));
        }
    }

    /**
     * EN: The user's saved permissions, sorted.
     * AR: صلاحيات اليوزر المحفوظة، مرتبة.
     *
     * @return list<string>
     */
    private function permissionsOf(User $user): array
    {
        return DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->orderBy('permission')
            ->pluck('permission')
            ->all();
    }
}
