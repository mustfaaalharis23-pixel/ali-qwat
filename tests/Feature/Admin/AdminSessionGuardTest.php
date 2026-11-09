<?php

namespace Tests\Feature\Admin;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSessionGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A user made inactive while signed in is signed out on their next request.
     * AR: اليوزر اللي ينوقف وهو داخل يطلع من الجلسة بأول طلب بعدها.
     */
    public function test_an_inactive_user_is_signed_out(): void
    {
        $user = User::factory()->create(['is_active' => true, 'is_super' => true]);

        $this->actingAs($user)->getJson('/admin-api/navigation')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->getJson('/admin-api/navigation')->assertUnauthorized();
        $this->assertGuest();
    }

    /**
     * EN: A deleted user is signed out on their next request too.
     * AR: اليوزر المحذوف هم يطلع من الجلسة بأول طلب.
     */
    public function test_a_deleted_user_is_signed_out(): void
    {
        $user = User::factory()->create(['is_active' => true, 'is_super' => true]);
        $this->actingAs($user);
        $user->delete();

        $this->getJson('/admin-api/navigation')->assertUnauthorized();
    }

    /**
     * EN: The admin application gets links to stored files relative to the site, so they open on any host and port.
     * AR: تطبيق الإدارة ياخذ روابط الملفات نسبية للموقع، فتفتح على أي عنوان وبورت.
     */
    public function test_file_links_are_relative(): void
    {
        $super = User::factory()->create(['is_active' => true, 'is_super' => true]);
        $item = Item::factory()->create(['image' => 'items/x.png']);

        $image = $this->actingAs($super)->getJson("/admin-api/items/{$item->id}")->assertOk()->json('data.image_url') ?? $this->getJson("/admin-api/items/{$item->id}")->json('data.image');

        $this->assertStringStartsWith('/storage/items/x.png', (string) $image);
    }
}
