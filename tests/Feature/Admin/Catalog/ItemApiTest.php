<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\LensPower;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
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
     * EN: Excel previews the rows before saving a batch without images, links supplier and category, and permits a later photo.
     * AR: Excel يعرض السطور قبل حفظ المواد دفعة وحدة بلا صور، ويربط المورد والفئة وتنگدر تضيف الصورة بعدين.
     */
    public function test_excel_previews_and_imports_multiple_items_without_images(): void
    {
        Storage::fake('public');
        $category = ItemCategory::factory()->create(['name' => 'Reading']);
        $supplier = Supplier::factory()->create(['name_zh' => '眼镜工厂']);
        $this->actingAs($this->userWith('item.view', 'item.create', 'item.update'));

        $file = $this->itemsWorkbook([
            ['001', 20, 'Reading', '眼镜工厂', 15000, 'Metal'],
            ['FRAME-2', 24],
        ]);

        $this->postJson('/admin-api/items/excel', ['file' => $file])->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.name', '001')
            ->assertJsonPath('rows.0.row', 4)
            ->assertJsonPath('rows.0.category', 'Reading')
            ->assertJsonPath('rows.0.errors', []);
        $this->assertDatabaseCount('items', 0);

        $this->postJson('/admin-api/items/excel/import', ['file' => $file, 'rows' => [4, 5]])
            ->assertCreated()->assertJsonPath('count', 2);

        $item = Item::query()->where('name', '001')->sole();
        $this->assertNull($item->image);
        $this->assertSame($category->id, $item->item_category_id);
        $this->assertSame($supplier->id, $item->supplier_id);
        $this->assertSame(15000.0, $item->sale_price);
        $this->assertSame('001', $item->specificDescription->name);
        $this->assertDatabaseHas('items', ['name' => 'FRAME-2', 'image' => null, 'sale_price' => 0]);

        $this->post("/admin-api/items/{$item->id}", [
            '_method' => 'PUT', 'name' => $item->name, 'pieces_per_packet' => 20,
            'image' => UploadedFile::fake()->image('frame.png'),
        ], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertExists($item->fresh()->image);

        $this->postJson('/admin-api/items/excel/import', ['file' => $file, 'rows' => [4, 5]])
            ->assertJsonValidationErrors(['rows.4', 'rows.5']);
        $this->assertDatabaseCount('items', 2);
    }

    /**
     * EN: Invalid data is reported on its original row and valid rows can be imported separately.
     * AR: البيانات الغلط تبين برقم سطرها، ونكدر نخلي بس السطور السليمة تنحفظ.
     */
    public function test_excel_reports_bad_rows_and_imports_only_selected_valid_rows(): void
    {
        Item::factory()->create(['name' => 'EXISTING']);
        $this->actingAs($this->userWith('item.create'));
        $file = $this->itemsWorkbook([
            ['existing', 20],
            [],
            ['BAD', 22, 'Unknown', null, -1],
            ['READY', 24],
        ]);

        $preview = $this->postJson('/admin-api/items/excel', ['file' => $file])->assertOk()->json('rows');
        $this->assertSame([4, 6, 7], array_column($preview, 'row'));
        $this->assertArrayHasKey('name', $preview[0]['errors']);
        $this->assertSame(['pieces_per_packet', 'category', 'sale_price'], array_keys($preview[1]['errors']));
        $this->assertSame([], $preview[2]['errors']);

        $this->postJson('/admin-api/items/excel/import', ['file' => $file, 'rows' => [6, 7]])->assertJsonValidationErrors('rows.6');
        $this->assertDatabaseMissing('items', ['name' => 'READY']);

        $this->postJson('/admin-api/items/excel/import', ['file' => $file, 'rows' => [7]])->assertCreated()->assertJsonPath('count', 1);
        $this->assertDatabaseHas('items', ['name' => 'READY']);
    }

    /**
     * EN: Downloading the workbook, previewing it and importing it require the item creation permission.
     * AR: تنزيل القالب ومعاينته واستيراده تحتاج صلاحية إنشاء مادة.
     */
    public function test_items_excel_requires_create_permission(): void
    {
        $this->actingAs($this->userWith('item.view'));

        $this->getJson('/admin-api/items/excel/template')->assertForbidden();
        $this->postJson('/admin-api/items/excel')->assertForbidden();
        $this->postJson('/admin-api/items/excel/import')->assertForbidden();
    }

    /** @param list<list<string|int|null>> $rows */
    private function itemsWorkbook(array $rows): UploadedFile
    {
        $download = $this->get('/admin-api/items/excel/template?languages[]=ar&languages[]=en&languages[]=zh_CN')
            ->assertDownload('AKA-items-template.xlsx')->streamedContent();
        $template = UploadedFile::fake()->createWithContent('blank.xlsx', $download);
        $reader = new Reader;
        $reader->open($template->getPathname());
        $path = tempnam(sys_get_temp_dir(), 'items-excel-');
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if ($number > 3) {
                        break;
                    }

                    $writer->addRow($row);
                }
                break;
            }

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();

            return UploadedFile::fake()->createWithContent('items.xlsx', file_get_contents($path));
        } finally {
            $reader->close();
            unlink($path);
        }
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
     * EN: Assigning a category updates only the selected items, and records each change in the audit log.
     * AR: تعيين الفئة يغيّر المواد المحددة بس، وكل تغيير ينكتب بسجل التدقيق.
     */
    public function test_the_same_category_is_assigned_to_selected_items(): void
    {
        $previous = ItemCategory::factory()->create();
        $category = ItemCategory::factory()->create();
        $first = Item::factory()->create(['item_category_id' => $previous->id]);
        $second = Item::factory()->inactive()->create();
        $unselected = Item::factory()->create(['item_category_id' => $previous->id]);
        $this->actingAs($this->userWith('item.view', 'item.update'));

        $this->postJson('/admin-api/items/category', ['ids' => [$first->id, $second->id], 'item_category_id' => $category->id])
            ->assertOk()->assertJsonPath('count', 2);

        foreach ([$first, $second] as $item) {
            $this->assertSame($category->id, $item->fresh()->item_category_id);
            $this->assertDatabaseHas('activity_log', ['subject_type' => $item->getMorphClass(), 'subject_id' => $item->id, 'event' => 'updated']);
        }
        $this->assertSame($previous->id, $unselected->fresh()->item_category_id);
    }

    /**
     * EN: Assigning a category to a selection requires permission to edit items.
     * AR: تعيين فئة للمواد المحددة يحتاج صلاحية تعديل المواد.
     */
    public function test_bulk_category_assignment_requires_update_permission(): void
    {
        $item = Item::factory()->create();
        $category = ItemCategory::factory()->create();
        $this->actingAs($this->userWith('item.view'));

        $this->postJson('/admin-api/items/category', ['ids' => [$item->id], 'item_category_id' => $category->id])->assertForbidden();

        $this->assertNull($item->fresh()->item_category_id);
    }

    /**
     * EN: An unavailable category or a deleted item in the selection prevents the whole batch from changing.
     * AR: الفئة المحذوفة أو مادة محذوفة ضمن التحديد توقف التغيير لكل الدفعة.
     */
    public function test_bulk_category_assignment_rejects_unavailable_categories_and_items(): void
    {
        $first = Item::factory()->create();
        $deleted = Item::factory()->create();
        $deleted->delete();
        $category = ItemCategory::factory()->create();
        $unavailable = ItemCategory::factory()->create();
        $unavailable->delete();
        $this->actingAs($this->userWith('item.view', 'item.update'));

        $this->postJson('/admin-api/items/category', ['ids' => [$first->id], 'item_category_id' => $unavailable->id])
            ->assertJsonValidationErrors('item_category_id');
        $this->postJson('/admin-api/items/category', ['ids' => [$first->id, $deleted->id], 'item_category_id' => $category->id])
            ->assertJsonValidationErrors('ids');

        $this->assertNull($first->fresh()->item_category_id);
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
