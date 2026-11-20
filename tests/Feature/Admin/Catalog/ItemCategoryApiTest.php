<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemCategoryApiTest extends TestCase
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
     * EN: The categories are listed with their item counts, and a used category offers no delete.
     * AR: الفئات تنعرض ويا عدد موادها، والفئة المستخدمة ما بيها حذف.
     */
    public function test_the_list_counts_the_items(): void
    {
        $used = ItemCategory::factory()->create(['name' => 'Frames']);
        ItemCategory::factory()->create(['name' => 'Lenses']);
        Item::factory()->create(['item_category_id' => $used->id]);

        $response = $this->actingAs($this->userWith('item_category.view', 'item_category.delete'))
            ->getJson('/admin-api/item-categories?sort=name&direction=asc')->assertOk();

        $this->assertSame([1, 0], array_column($response->json('data'), 'items_count'));
        $this->assertFalse($response->json('data.0.can.delete'));
        $this->assertTrue($response->json('data.1.can.delete'));
    }

    /**
     * EN: A category is created and renamed with a unique name.
     * AR: الفئة تنضاف ويتغير اسمها باسم ما يتكرر.
     */
    public function test_create_and_update_with_a_unique_name(): void
    {
        ItemCategory::factory()->create(['name' => 'Frames']);
        $this->actingAs($this->userWith('item_category.view', 'item_category.create', 'item_category.update'));

        $id = $this->postJson('/admin-api/item-categories', ['name' => 'Lenses'])->assertCreated()->json('data.id');
        $this->postJson('/admin-api/item-categories', ['name' => 'Frames'])->assertJsonValidationErrors('name');
        $this->putJson("/admin-api/item-categories/{$id}", ['name' => 'Contact lenses'])->assertOk()->assertJsonPath('data.name', 'Contact lenses');
    }

    /**
     * EN: An unused category is deleted and restored; a used one is refused.
     * AR: الفئة الغير مستخدمة تنحذف وترجع، والمستخدمة تنرفض.
     */
    public function test_delete_and_restore(): void
    {
        $free = ItemCategory::factory()->create();
        $used = ItemCategory::factory()->create();
        Item::factory()->create(['item_category_id' => $used->id]);
        $this->actingAs($this->userWith('item_category.view', 'item_category.delete', 'item_category.restore'));

        $this->deleteJson("/admin-api/item-categories/{$used->id}")->assertStatus(422);
        $this->deleteJson("/admin-api/item-categories/{$free->id}")->assertNoContent();
        $this->postJson("/admin-api/item-categories/{$free->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($free);
    }

    /**
     * EN: An item is saved with a category and can be left without one; a deleted category cannot be chosen.
     * AR: المادة تنحفظ ويا فئة، وتكدر تبقى بدون فئة؛ والفئة المحذوفة ما تنختار.
     */
    public function test_an_item_is_saved_with_a_category_or_without_one(): void
    {
        $item = Item::factory()->create(['name' => 'Frame', 'pieces_per_packet' => 20]);
        $category = ItemCategory::factory()->create(['name' => 'Frames']);
        $deleted = ItemCategory::factory()->create();
        $deleted->delete();
        $this->actingAs($this->userWith('item.view', 'item.update'));

        $this->post("/admin-api/items/{$item->id}", ['_method' => 'PUT', 'name' => 'Frame', 'pieces_per_packet' => 20, 'item_category_id' => $category->id], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.item_category_id', $category->id)
            ->assertJsonPath('data.category.name', 'Frames');

        $this->post("/admin-api/items/{$item->id}", ['_method' => 'PUT', 'name' => 'Frame', 'pieces_per_packet' => 20, 'item_category_id' => $deleted->id], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('item_category_id');
        $this->assertSame($category->id, $item->fresh()->item_category_id);

        $this->post("/admin-api/items/{$item->id}", ['_method' => 'PUT', 'name' => 'Frame', 'pieces_per_packet' => 20, 'item_category_id' => ''], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNull($item->fresh()->item_category_id);
    }

    /**
     * EN: The items list is filtered by category, and each item shows its category.
     * AR: قائمة المواد تتفلتر حسب الفئة، وكل مادة تبيّن فئتها.
     */
    public function test_the_items_list_is_filtered_by_category(): void
    {
        $frames = ItemCategory::factory()->create(['name' => 'Frames']);
        $lenses = ItemCategory::factory()->create(['name' => 'Lenses']);
        Item::factory()->create(['name' => 'Round frame', 'item_category_id' => $frames->id]);
        Item::factory()->create(['name' => 'Blue lens', 'item_category_id' => $lenses->id]);
        Item::factory()->create(['name' => 'Without category']);

        $this->actingAs($this->userWith('item.view'))
            ->getJson("/admin-api/items?filter[item_category_id]={$frames->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Round frame')
            ->assertJsonPath('data.0.category.name', 'Frames');
    }
}
