<?php

namespace Tests\Unit;

use App\Models\Audit;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\SpecificDescription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemSpecificDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * EN: Creating an item creates its specific description under the purchases description, with the item's name.
     * AR: إضافة مادة تنشئ وصفها الخاص تحت وصف الشراء، بنفس اسم المادة.
     */
    public function test_creating_an_item_creates_its_description_under_the_purchases_description(): void
    {
        $item = Item::factory()->create(['name' => 'Rice']);

        $description = $item->specificDescription;

        $this->assertNotNull($description);
        $this->assertSame('Rice', $description->name);
        $this->assertSame(GeneralDescription::purchases()->id, $description->general_description_id);
    }

    /**
     * EN: Renaming an item renames its specific description.
     * AR: تغيير اسم المادة يغير اسم وصفها الخاص.
     */
    public function test_renaming_an_item_renames_its_description(): void
    {
        $item = Item::factory()->create(['name' => 'Rice']);

        $item->update(['name' => 'Brown rice']);

        $this->assertSame('Brown rice', $item->fresh()->specificDescription->name);
    }

    /**
     * EN: Saving an item again does not create a second specific description.
     * AR: حفظ المادة مرة ثانية ما ينشئ وصف خاص ثاني.
     */
    public function test_saving_an_item_again_keeps_one_description(): void
    {
        $item = Item::factory()->create();

        $item->update(['is_active' => false]);

        $this->assertSame(1, SpecificDescription::query()->where('item_id', $item->id)->count());
    }

    /**
     * EN: The item's specific description is saved without an audit, because it is a copy of the item.
     * AR: الوصف الخاص للمادة ينحفظ بدون audit، لأنه نسخة من المادة.
     */
    public function test_item_description_records_no_audit(): void
    {
        Item::factory()->create();

        $this->assertSame(0, Audit::query()->where('subject_type', (new SpecificDescription)->getMorphClass())->count());
    }

    /**
     * EN: Force deleting an item force deletes its specific description.
     * AR: الحذف النهائي للمادة يحذف وصفها الخاص نهائياً.
     */
    public function test_force_deleting_an_item_force_deletes_its_description(): void
    {
        $item = Item::factory()->create();
        $descriptionId = $item->specificDescription->id;

        $item->forceDelete();

        $this->assertDatabaseMissing('specific_descriptions', ['id' => $descriptionId]);
    }

    /**
     * EN: An item used in an invoice cannot be force deleted.
     * AR: المادة المستخدمة بفاتورة ما تنحذف نهائياً.
     */
    public function test_item_used_in_an_invoice_cannot_be_force_deleted(): void
    {
        $item = Item::factory()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 10,
            'price' => 1000,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23503]');

        $item->forceDelete();
    }
}
