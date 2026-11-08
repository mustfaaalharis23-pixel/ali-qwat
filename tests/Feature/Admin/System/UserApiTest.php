<?php

namespace Tests\Feature\Admin\System;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    /**
     * @return list<string>
     */
    private function permissionsOf(User $user): array
    {
        return DB::table('user_permissions')->where('user_id', $user->id)->orderBy('permission')->pluck('permission')->all();
    }

    /**
     * EN: The users list is searched by name; only a super user sees who is super.
     * AR: قائمة اليوزرات تنبحث بالاسم؛ وبس السوبر يشوف منو سوبر.
     */
    public function test_the_list_hides_the_super_flag_from_others(): void
    {
        User::factory()->create(['name' => 'Zainab']);

        $this->actingAs($this->userWith())->getJson('/admin-api/users')->assertForbidden();

        $response = $this->actingAs($this->userWith('user.view'))->getJson('/admin-api/users?search=zain')->assertOk();
        $this->assertSame(['Zainab'], array_column($response->json('data'), 'name'));
        $this->assertArrayNotHasKey('is_super', $response->json('data.0'));

        $this->actingAs($this->superUser())->getJson('/admin-api/users?search=zain')->assertJsonPath('data.0.is_super', false);
    }

    /**
     * EN: A user is created with a password and the active and cashier flags; a non-super user cannot make a super user.
     * AR: اليوزر ينضاف بباسورد وعلامات الفعّال والكاشير؛ واليوزر العادي ما يگدر يسوي سوبر.
     */
    public function test_create(): void
    {
        $this->actingAs($this->userWith('user.view', 'user.create'));

        $this->postJson('/admin-api/users', ['name' => 'Ali', 'email' => 'not-an-email'])->assertJsonValidationErrors(['email', 'password']);

        $id = $this->postJson('/admin-api/users', [
            'name' => 'Ali', 'email' => 'ali@example.com', 'password' => 'secret-pass', 'is_active' => true, 'is_cashier' => true, 'is_super' => true,
        ])->assertCreated()->json('data.id');

        $user = User::query()->findOrFail($id);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->is_cashier);
        $this->assertFalse((bool) $user->is_super);
        $this->assertTrue(Hash::check('secret-pass', $user->password));

        $this->postJson('/admin-api/users', ['name' => 'Ali 2', 'email' => 'ali@example.com', 'password' => 'x'])->assertJsonValidationErrors('email');
    }

    /**
     * EN: An edit keeps the password when it is left empty; only a super user changes the permissions and the super flag.
     * AR: التعديل يخلي الباسورد إذا تُرك فارغ؛ وبس السوبر يغيّر الصلاحيات وعلامة السوبر.
     */
    public function test_update_and_permissions(): void
    {
        $target = $this->userWith('color.view', 'item.view');
        $password = $target->password;

        $this->actingAs($this->userWith('user.update'))
            ->putJson("/admin-api/users/{$target->id}", ['name' => 'Renamed', 'email' => $target->email, 'password' => '', 'is_active' => true, 'is_cashier' => false, 'is_super' => true, 'permissions' => ['user.view']])
            ->assertOk();
        $target->refresh();
        $this->assertSame('Renamed', $target->name);
        $this->assertSame($password, $target->password);
        $this->assertFalse((bool) $target->is_super);
        $this->assertSame(['color.view', 'item.view'], $this->permissionsOf($target));

        $this->actingAs($this->superUser())
            ->putJson("/admin-api/users/{$target->id}", ['name' => 'Renamed', 'email' => $target->email, 'is_active' => true, 'is_cashier' => false, 'is_super' => false, 'permissions' => ['user.view', 'app.orders']])
            ->assertOk();
        $this->assertSame(['app.orders', 'user.view'], $this->permissionsOf($target));

        $this->getJson("/admin-api/users/{$target->id}")->assertJsonPath('data.permissions', ['app.orders', 'user.view']);
    }

    /**
     * EN: The permission tree has one group per model, the lenses, and the sales application; only a super user reads it or copies permissions.
     * AR: شجرة الصلاحيات بيها مجموعة لكل موديل، والعدسات، وتطبيق البيع؛ وبس السوبر يقراها أو ينسخ الصلاحيات.
     */
    public function test_permission_groups_and_copy(): void
    {
        $source = $this->userWith('color.view', 'app.checkout');

        $this->actingAs($this->userWith('user.view', 'user.update'))->getJson('/admin-api/users/permission-groups')->assertForbidden();
        $this->getJson("/admin-api/users/{$source->id}/permissions")->assertForbidden();

        $groups = $this->actingAs($this->superUser())->getJson('/admin-api/users/permission-groups')->assertOk()->json('data');
        $keys = array_column($groups, 'key');
        $this->assertContains('color', $keys);
        $this->assertContains('app', $keys);
        $color = collect($groups)->firstWhere('key', 'color');
        $this->assertContains('color.view', array_column($color['permissions'], 'name'));

        $this->getJson("/admin-api/users/{$source->id}/permissions")->assertOk()->assertJsonPath('data', ['app.checkout', 'color.view']);
    }

    /**
     * EN: A user is deleted and restored, but never deleted forever, not even by a super user.
     * AR: اليوزر ينحذف ويرجع، بس ما ينحذف نهائياً، حتى من السوبر.
     */
    public function test_users_are_never_force_deleted(): void
    {
        $target = User::factory()->create();
        $this->actingAs($this->superUser());

        $this->deleteJson("/admin-api/users/{$target->id}")->assertNoContent();
        $this->getJson('/admin-api/users?trashed=only')->assertJsonPath('data.0.can.force_delete', false)->assertJsonPath('data.0.can.restore', true);
        $this->deleteJson("/admin-api/users/{$target->id}/force")->assertForbidden();
        $this->postJson("/admin-api/users/{$target->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($target);
    }
}
