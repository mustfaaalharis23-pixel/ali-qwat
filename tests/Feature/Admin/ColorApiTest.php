<?php

namespace Tests\Feature\Admin;

use App\Models\Color;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ColorApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);
        }

        return $user;
    }

    /**
     * EN: The colors list is searched by name and sorted, page by page, with what the user may do on each row.
     * AR: قائمة الألوان تنبحث بالاسم وتترتب، صفحة صفحة، ويا الشي اللي يگدر اليوزر يسويه بكل صف.
     */
    public function test_the_list_is_searched_and_sorted(): void
    {
        Color::factory()->create(['name' => 'Red']);
        Color::factory()->create(['name' => 'Blue']);
        Color::factory()->create(['name' => 'Black']);

        $response = $this->actingAs($this->userWith('color.view'))
            ->getJson('/admin-api/colors?search=bl&sort=name&direction=desc')
            ->assertOk();

        $this->assertSame(['Blue', 'Black'], array_column($response->json('data'), 'name'));
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertFalse($response->json('data.0.can.update'));
        $this->assertFalse($response->json('can.create'));
    }

    /**
     * EN: A user with the permissions creates and renames a color; a name already taken is refused.
     * AR: اليوزر اللي عنده الصلاحيات يضيف لون ويغيّر اسمه، والاسم المأخوذ ينرفض.
     */
    public function test_create_and_update_with_a_unique_name(): void
    {
        Color::factory()->create(['name' => 'Red']);
        $this->actingAs($this->userWith('color.view', 'color.create', 'color.update'));

        $id = $this->postJson('/admin-api/colors', ['name' => 'Green'])->assertCreated()->json('data.id');
        $this->postJson('/admin-api/colors', ['name' => 'Red'])->assertJsonValidationErrors('name');
        $this->putJson("/admin-api/colors/{$id}", ['name' => 'Olive'])->assertOk()->assertJsonPath('data.name', 'Olive');
    }

    /**
     * EN: Without the create permission a color cannot be added.
     * AR: بدون صلاحية الإضافة ما ينضاف لون.
     */
    public function test_create_needs_the_permission(): void
    {
        $this->actingAs($this->userWith('color.view'))->postJson('/admin-api/colors', ['name' => 'Green'])->assertForbidden();
    }

    /**
     * EN: A deleted color shows only with the deleted filter, and it can be restored.
     * AR: اللون المحذوف يطلع بس ويا فلتر المحذوفات، ويرجع بالاسترجاع.
     */
    public function test_delete_and_restore(): void
    {
        $color = Color::factory()->create(['name' => 'Red']);
        $this->actingAs($this->userWith('color.view', 'color.delete', 'color.restore'));

        $this->deleteJson("/admin-api/colors/{$color->id}")->assertNoContent();
        $this->getJson('/admin-api/colors')->assertJsonCount(0, 'data');
        $this->getJson('/admin-api/colors?trashed=only')->assertJsonCount(1, 'data')->assertJsonPath('data.0.can.restore', true);
        $this->postJson("/admin-api/colors/{$color->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($color);
    }
}
