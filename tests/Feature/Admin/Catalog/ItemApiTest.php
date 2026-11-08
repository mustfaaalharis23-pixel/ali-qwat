<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemApiTest extends TestCase
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
     * EN: The items list shows the active items by default; the status filter shows the inactive ones or all of them, and the list is searched by name.
     * AR: قائمة المواد تعرض المواد الفعالة افتراضياً؛ فلتر الحالة يعرض غير الفعالة أو كلهن، والقائمة تنبحث بالاسم.
     */
    public function test_the_list_shows_the_active_items_by_default(): void
    {
        Item::factory()->create(['name' => 'Round frame']);
        Item::factory()->create(['name' => 'Square frame']);
        Item::factory()->inactive()->create(['name' => 'Old frame']);
        $this->actingAs($this->userWith('item.view'));

        $this->getJson('/admin-api/items')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/admin-api/items?filter[status]=inactive')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Old frame');
        $this->getJson('/admin-api/items?filter[status]=all')->assertJsonCount(3, 'data');
        $this->getJson('/admin-api/items?search=round')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Round frame');
    }

    /**
     * EN: The list is filtered by supplier.
     * AR: القائمة تتفلتر حسب المورّد.
     */
    public function test_the_list_is_filtered_by_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        Item::factory()->create(['name' => 'With supplier', 'supplier_id' => $supplier->id]);
        Item::factory()->create(['name' => 'Without supplier']);

        $this->actingAs($this->userWith('item.view'))
            ->getJson("/admin-api/items?filter[supplier_id]={$supplier->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'With supplier')
            ->assertJsonPath('data.0.supplier.name', $supplier->name_ar);
    }

    /**
     * EN: The cost price and the profit margin are sent only to a user who may see what the items cost.
     * AR: كلفة المادة وهامش الربح ينبعثن بس لليوزر اللي يگدر يشوف كلفة المواد.
     */
    public function test_the_cost_is_sent_only_to_a_user_who_may_see_it(): void
    {
        $item = Item::factory()->create(['sale_price' => 150]);
        $item->forceFill(['cost_price' => 100])->saveQuietly();

        $this->actingAs($this->userWith('item.view'))->getJson('/admin-api/items')
            ->assertJsonMissingPath('data.0.cost_price')
            ->assertJsonMissingPath('data.0.profit_margin')
            ->assertJsonPath('can.view_cost_price', false);

        $this->actingAs($this->userWith('item.view', 'item.view_cost_price'))->getJson('/admin-api/items')
            ->assertJsonPath('data.0.cost_price', 100)
            ->assertJsonPath('data.0.profit_margin', 50)
            ->assertJsonPath('can.view_cost_price', true);

        $this->actingAs($this->userWith('item.view'))->getJson("/admin-api/items/{$item->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.cost_price');
    }

    /**
     * EN: Sorting by the cost price is refused without the permission: the list keeps its own order, the newest first.
     * AR: الترتيب حسب الكلفة ما ينقبل بدون الصلاحية: القائمة تبقى بترتيبها، الأحدث أول.
     */
    public function test_the_cost_cannot_be_sorted_on_without_the_permission(): void
    {
        $cheap = Item::factory()->create();
        $cheap->forceFill(['cost_price' => 10])->saveQuietly();
        $dear = Item::factory()->create();
        $dear->forceFill(['cost_price' => 500])->saveQuietly();

        $this->actingAs($this->userWith('item.view'))
            ->getJson('/admin-api/items?sort=cost_price&direction=asc')
            ->assertJsonPath('data.0.id', $dear->id);

        $this->actingAs($this->userWith('item.view', 'item.view_cost_price'))
            ->getJson('/admin-api/items?sort=cost_price&direction=asc')
            ->assertJsonPath('data.0.id', $cheap->id);
    }

    /**
     * EN: A user with the permission creates an item with its image on the public disk; the image and a packet of 20 or 24 pieces are required.
     * AR: اليوزر اللي عنده الصلاحية يضيف مادة وصورتها تنحفظ عالقرص العام؛ الصورة وباكيت 20 أو 24 قطعة مطلوبات.
     */
    public function test_an_item_is_created_with_its_image(): void
    {
        Storage::fake('public');
        $supplier = Supplier::factory()->create();
        $this->actingAs($this->userWith('item.view', 'item.create'));

        $this->postJson('/admin-api/items', ['name' => 'Frame', 'pieces_per_packet' => 22])
            ->assertJsonValidationErrors(['image', 'pieces_per_packet']);

        $id = $this->post('/admin-api/items', [
            'name' => 'Frame',
            'supplier_id' => $supplier->id,
            'pieces_per_packet' => 24,
            'description' => 'Metal',
            'is_active' => '1',
            'sale_price' => '',
            'image' => UploadedFile::fake()->image('frame.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $item = Item::query()->findOrFail($id);
        $this->assertSame('Frame', $item->name);
        $this->assertSame(24, $item->pieces_per_packet);
        $this->assertSame(0.0, $item->sale_price);
        $this->assertSame($supplier->id, $item->supplier_id);
        Storage::disk('public')->assertExists($item->image);
        $this->assertStringStartsWith('items/', $item->image);
    }

    /**
     * EN: Without the create permission an item cannot be added.
     * AR: بدون صلاحية الإضافة ما تنضاف مادة.
     */
    public function test_create_needs_the_permission(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWith('item.view'))
            ->post('/admin-api/items', ['name' => 'Frame', 'pieces_per_packet' => 24, 'image' => UploadedFile::fake()->image('frame.png')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    /**
     * EN: Updating an item without a new image keeps its image; a new image replaces it.
     * AR: تعديل المادة بدون صورة جديدة يخلي صورتها؛ والصورة الجديدة تبدلها.
     */
    public function test_update_keeps_or_replaces_the_image(): void
    {
        Storage::fake('public');
        $item = Item::factory()->create(['name' => 'Frame', 'image' => 'items/old.png', 'pieces_per_packet' => 20]);
        $this->actingAs($this->userWith('item.view', 'item.update'));

        $this->post("/admin-api/items/{$item->id}", ['_method' => 'PUT', 'name' => 'New frame', 'pieces_per_packet' => 20, 'sale_price' => '1,500'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New frame');
        $this->assertSame('items/old.png', $item->fresh()->image);
        $this->assertSame(1500.0, $item->fresh()->sale_price);

        $this->post("/admin-api/items/{$item->id}", ['_method' => 'PUT', 'name' => 'New frame', 'pieces_per_packet' => 20, 'image' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNotSame('items/old.png', $item->fresh()->image);
    }

    /**
     * EN: The item's page has what came in of each power and of each color, as numbers.
     * AR: صفحة المادة بيها اللي وصل من كل درجة ومن كل لون، كأرقام.
     */
    public function test_the_page_has_the_quantities_by_power_and_by_color(): void
    {
        $item = Item::factory()->create();
        $power = LensPower::factory()->create(['value' => 1.5]);
        $invoice = Invoice::factory()->purchase()->confirmed()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 10,
            'pieces_per_packet' => 12,
            'price' => 1000,
        ])->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => 10]);

        $response = $this->actingAs($this->userWith('item.view'))->getJson("/admin-api/items/{$item->id}")->assertOk();

        $this->assertEquals(
            [['power' => '1.50', 'arrived_packets' => 10, 'arrived_pieces' => 120, 'incoming_packets' => 0, 'incoming_pieces' => 0, 'remaining_pieces' => 120]],
            $response->json('data.quantities_by_power'),
        );
        $this->assertSame([], $response->json('data.quantities_by_color'));
        $this->assertTrue($response->json('data.has_powers'));
    }

    /**
     * EN: A deleted item still opens on its page, with the restore ability.
     * AR: المادة المحذوفة تنفتح صفحتها، ويا إمكانية الاسترجاع.
     */
    public function test_a_deleted_item_opens(): void
    {
        $item = Item::factory()->create();
        $item->delete();

        $this->actingAs($this->userWith('item.view', 'item.restore'))
            ->getJson("/admin-api/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.can.restore', true);
    }

    /**
     * EN: The item's cards: the three quantity cards for everyone, and the purchase price and profit margin cards only for a user who may see the cost.
     * AR: كروت المادة: كروت الكميات الثلاثة للكل، وكروت أسعار الشراء وهامش الربح بس لليوزر اللي يگدر يشوف الكلفة.
     */
    public function test_the_item_cards_follow_the_cost_permission(): void
    {
        $item = Item::factory()->stocked(10, 100)->create();

        $response = $this->actingAs($this->userWith('item.view'))->getJson("/admin-api/items/{$item->id}/metrics")->assertOk();
        $this->assertSame(['available_quantity', 'incoming_quantity', 'sold_quantity'], array_column($response->json('data'), 'key'));
        $this->assertEquals(10, $response->json('data.0.value.raw'));

        $response = $this->actingAs($this->userWith('item.view', 'item.view_cost_price'))->getJson("/admin-api/items/{$item->id}/metrics")->assertOk();
        $this->assertCount(8, $response->json('data'));
        $this->assertEquals($item->fresh()->cost_price, collect($response->json('data'))->firstWhere('key', 'last_purchase_price')['value']['raw']);
    }

    /**
     * EN: The cards above the items list: all the items, the active ones, the inactive ones, and the ones without a supplier.
     * AR: الكروت فوق قائمة المواد: كل المواد، الفعالة، غير الفعالة، واللي بلا مورّد.
     */
    public function test_the_list_cards(): void
    {
        Item::factory()->create();
        Item::factory()->inactive()->create();

        $response = $this->actingAs($this->userWith('item.view'))->getJson('/admin-api/items/metrics')->assertOk();

        $this->assertSame(['item_count', 'active_item_count', 'inactive_item_count', 'item_without_supplier_count'], array_column($response->json('data'), 'key'));
        $this->assertEquals(1, $response->json('data.2.value.raw'));
    }

    /**
     * EN: Items are deleted, restored, and deleted together from the list, each one checked on its own.
     * AR: المواد تنحذف وترجع سوة من القائمة، وكل وحدة تنفحص لوحدها.
     */
    public function test_delete_restore_and_bulk_actions(): void
    {
        $first = Item::factory()->create();
        $second = Item::factory()->create();
        $this->actingAs($this->userWith('item.view', 'item.delete', 'item.restore'));

        $this->deleteJson("/admin-api/items/{$first->id}")->assertNoContent();
        $this->assertSoftDeleted($first);
        $this->postJson("/admin-api/items/{$first->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($first);

        $this->postJson('/admin-api/items/bulk', ['action' => 'delete', 'ids' => [$first->id, $second->id]])->assertOk()->assertJsonPath('count', 2);
        $this->assertSoftDeleted($second);
        $this->postJson('/admin-api/items/bulk', ['action' => 'force_delete', 'ids' => [$first->id]])->assertForbidden();
        $this->postJson('/admin-api/items/bulk', ['action' => 'restore', 'ids' => [$first->id, $second->id]])->assertOk();
        $this->assertNotSoftDeleted($second);
    }

    /**
     * EN: The sale price is set by a user who can edit items; only the sale price is saved.
     * AR: سعر البيع يحدده اليوزر اللي يگدر يعدل المواد؛ ينحفظ سعر البيع بس.
     */
    public function test_the_sale_price_is_set(): void
    {
        $item = Item::factory()->create(['sale_price' => 50]);

        $this->actingAs($this->userWith('item.view'))->putJson("/admin-api/items/{$item->id}/sale-price", ['sale_price' => 200])->assertForbidden();

        $this->actingAs($this->userWith('item.view', 'item.update'));
        $this->putJson("/admin-api/items/{$item->id}/sale-price", ['sale_price' => -1])->assertJsonValidationErrors('sale_price');
        $this->putJson("/admin-api/items/{$item->id}/sale-price", ['sale_price' => '1,250.5'])->assertOk()->assertJsonPath('data.sale_price', 1250.5);
    }

    /**
     * EN: An inactive item is activated, and its stock is worked out again, by a user who can edit items.
     * AR: المادة غير الفعالة تتفعّل، وكميتها تنحسب من جديد، من اليوزر اللي يگدر يعدل المواد.
     */
    public function test_activate_and_recalculate_the_stock(): void
    {
        $item = Item::factory()->inactive()->stocked(10, 100)->create();
        $item->forceFill(['available_quantity' => -5])->saveQuietly();

        $this->actingAs($this->userWith('item.view'))->postJson("/admin-api/items/{$item->id}/activate")->assertForbidden();

        $this->actingAs($this->userWith('item.view', 'item.update'));
        $this->postJson("/admin-api/items/{$item->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);
        $this->postJson("/admin-api/items/{$item->id}/recalculate-stock")->assertOk();
        $this->assertEquals(10, $item->fresh()->available_quantity);
    }

    /**
     * EN: The supplier is set on one item, or on several selected items at once.
     * AR: المورّد ينحط على مادة وحدة، أو على عدة مواد مختارة مرة وحدة.
     */
    public function test_the_supplier_is_set(): void
    {
        $supplier = Supplier::factory()->create();
        $first = Item::factory()->create();
        $second = Item::factory()->create();
        $this->actingAs($this->userWith('item.view', 'item.update'));

        $this->putJson("/admin-api/items/{$first->id}/supplier", [])->assertJsonValidationErrors('supplier_id');
        $this->putJson("/admin-api/items/{$first->id}/supplier", ['supplier_id' => $supplier->id])->assertOk();
        $this->assertSame($supplier->id, $first->fresh()->supplier_id);

        $this->postJson('/admin-api/items/supplier', ['ids' => [$second->id], 'supplier_id' => $supplier->id])->assertOk();
        $this->assertSame($supplier->id, $second->fresh()->supplier_id);
    }
}
