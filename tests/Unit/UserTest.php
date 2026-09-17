<?php

namespace Tests\Unit;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A new user saved without is_super and is_active gets false for both from the database.
     * AR: يوزر جديد ينحفظ بدون ما نحدد is_super و is_active، فالداتابيس تنطي الاثنين false.
     */
    public function test_new_user_is_not_super_and_not_active_by_default(): void
    {
        DB::table('users')->insert([
            'name' => 'Ali',
            'email' => 'ali@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'ali@example.com',
            'is_super' => false,
            'is_active' => false,
        ]);
    }

    /**
     * EN: Deleting a user is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف اليوزر يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_a_user_soft_deletes_it(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted($user);
    }

    /**
     * EN: A deleted user's permissions stay in the user_permissions table and are not removed.
     * AR: صلاحيات اليوزر المحذوف تبقى بجدول user_permissions وما تنمسح.
     */
    public function test_deleting_a_user_keeps_its_permissions(): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => 'invoice.view',
        ]);

        $user->delete();

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'permission' => 'invoice.view',
        ]);
    }

    /**
     * EN: A super user can delete users, but can never force delete them, neither one nor many.
     * AR: is_super يكدر يحذف اليوزر، بس ما يكدر يحذفه نهائياً، لا واحد ولا مجموعة.
     */
    public function test_super_user_can_delete_users_but_never_force_delete_them(): void
    {
        $super = User::factory()->create(['is_super' => true]);
        $user = User::factory()->create();

        $this->assertTrue($super->can('delete', $user));
        $this->assertFalse($super->can('forceDelete', $user));
        $this->assertFalse($super->can('forceDeleteAny', User::class));
    }

    /**
     * EN: An active user that is not deleted can access the panel.
     * AR: اليوزر الـ is_active وغير المحذوف يدخل لوحة التحكم.
     */
    public function test_active_user_can_access_the_panel(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    /**
     * EN: A user whose is_active is false cannot access the panel.
     * AR: اليوزر اللي is_active مالته false ما يدخل لوحة التحكم.
     */
    public function test_inactive_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    /**
     * EN: A deleted user cannot access the panel, even if active.
     * AR: اليوزر المحذوف ما يدخل لوحة التحكم، حتى لو is_active.
     */
    public function test_deleted_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $user->delete();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }
}
