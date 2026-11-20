<?php

namespace Tests\Unit;

use App\Models\Item;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * EN: A new item saved without is_active gets true from the database.
     * AR: صنف جديد ينحفظ بدون ما نحدد is_active، فالداتابيس تنطيه true.
     */
    public function test_new_item_is_active_by_default(): void
    {
        DB::table('items')->insert([
            'name' => 'Rice',
            'image' => 'items/rice.jpg',
        ]);

        $this->assertDatabaseHas('items', [
            'name' => 'Rice',
            'is_active' => true,
        ]);
    }

    /**
     * EN: An item requires a name in the database.
     * AR: المادة تحتاج اسم حتى تنحفظ بقاعدة البيانات.
     */
    public function test_item_cannot_be_saved_without_a_name(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23502]');

        DB::table('items')->insert(['image' => 'items/rice.jpg']);
    }

    /**
     * EN: An item can be saved with an empty description and image.
     * AR: المادة تنحفظ بقاعدة البيانات بدون تفاصيل وصورة.
     */
    public function test_item_can_be_saved_without_a_description_or_image(): void
    {
        DB::table('items')->insert(['name' => 'Rice']);

        $this->assertDatabaseHas('items', [
            'name' => 'Rice',
            'description' => null,
            'image' => null,
        ]);
    }

    /**
     * EN: A long description (more than 255 characters) is saved in full.
     * AR: التفاصيل الطويلة (أكثر من 255 حرف) تنحفظ كاملة.
     */
    public function test_long_description_is_saved_in_full(): void
    {
        $description = str_repeat('Long rice description. ', 50);

        $item = Item::factory()->create(['description' => $description]);

        $this->assertSame($description, $item->fresh()->description);
    }

    /**
     * EN: Two items can have the same name.
     * AR: صنفين يكدرون ياخذون نفس الاسم.
     */
    public function test_two_items_can_have_the_same_name(): void
    {
        Item::factory()->create(['name' => 'Rice']);
        Item::factory()->create(['name' => 'Rice']);

        $this->assertSame(2, Item::where('name', 'Rice')->count());
    }

    /**
     * EN: Deleting an item is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف الصنف يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_an_item_soft_deletes_it(): void
    {
        $item = Item::factory()->create();

        $item->delete();

        $this->assertSoftDeleted($item);
    }

    /**
     * EN: A soft deleted item keeps its image file on the disk, so it can be restored.
     * AR: الصنف المحذوف soft delete تبقى صورته بالـ disk، حتى ينفع الاسترجاع.
     */
    public function test_soft_deleting_an_item_keeps_its_image_file(): void
    {
        $item = $this->itemWithImage('items/rice.jpg');

        $item->delete();

        Storage::disk('public')->assertExists('items/rice.jpg');
    }

    /**
     * EN: Force deleting an item removes its image file from the disk.
     * AR: الحذف النهائي للصنف يمسح ملف صورته من الـ disk.
     */
    public function test_force_deleting_an_item_deletes_its_image_file(): void
    {
        $item = $this->itemWithImage('items/rice.jpg');

        $item->forceDelete();

        Storage::disk('public')->assertMissing('items/rice.jpg');
    }

    /**
     * EN: Replacing an item's image removes the old file and keeps the new one.
     * AR: تبديل صورة الصنف يمسح الملف القديم ويبقي الجديد.
     */
    public function test_replacing_the_image_deletes_the_old_file(): void
    {
        $item = $this->itemWithImage('items/old.jpg');
        Storage::disk('public')->put('items/new.jpg', 'new image');

        $item->image = 'items/new.jpg';
        $item->save();

        Storage::disk('public')->assertMissing('items/old.jpg');
        Storage::disk('public')->assertExists('items/new.jpg');
    }

    /**
     * EN: Updating an item without changing its image keeps the image file.
     * AR: تعديل الصنف بدون تغيير صورته يبقي ملف الصورة.
     */
    public function test_updating_an_item_without_changing_the_image_keeps_the_file(): void
    {
        $item = $this->itemWithImage('items/rice.jpg');

        $item->name = 'White rice';
        $item->save();

        Storage::disk('public')->assertExists('items/rice.jpg');
    }

    /**
     * EN: Creates an item whose image file exists on the public disk.
     * AR: ينشئ صنف ملف صورته موجود بالـ public disk.
     */
    private function itemWithImage(string $path): Item
    {
        Storage::disk('public')->put($path, 'image');

        return Item::factory()->create(['image' => $path]);
    }
}
