<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedCategoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Legacy item categories are relinked by name even when IDs collide, and archived and uncategorized data are retained.
     * AR: فئات المواد القديمة تنربط بالاسم حتى إذا المعرفات متشابهة، والفئات المؤرشفة والمواد بلا فئة تبقى محفوظة.
     */
    public function test_the_migration_merges_names_and_preserves_existing_links(): void
    {
        $migration = require database_path('migrations/2026_11_21_095000_share_categories_between_items_and_invoices.php');
        $migration->down();

        $frames = InvoiceCategory::factory()->create(['name' => 'Frames']);
        $different = InvoiceCategory::factory()->create(['name' => 'Accessories']);
        $invoice = Invoice::factory()->purchase()->create(['invoice_category_id' => $frames->id]);
        $archivedId = $different->id + 10;
        DB::table('item_categories')->insert([
            ['id' => $frames->id, 'name' => 'Lenses', 'deleted_at' => null],
            ['id' => $different->id, 'name' => 'Frames', 'deleted_at' => null],
            ['id' => $archivedId, 'name' => 'Archived', 'deleted_at' => now()],
        ]);
        DB::selectOne("SELECT setval(pg_get_serial_sequence('item_categories', 'id'), ?, true)", [$archivedId]);
        $oldLensItem = Item::factory()->create(['item_category_id' => $frames->id]);
        $oldFrameItem = Item::factory()->create(['item_category_id' => $different->id]);
        $uncategorized = Item::factory()->create();

        $migration->up();

        $this->assertSame('Lenses', $oldLensItem->fresh()->category->name);
        $this->assertNotSame($frames->id, $oldLensItem->fresh()->item_category_id);
        $this->assertSame($frames->id, $oldFrameItem->fresh()->item_category_id);
        $this->assertSame($frames->id, $invoice->fresh()->invoice_category_id);
        $this->assertNull($uncategorized->fresh()->item_category_id);
        $this->assertSame(1, ItemCategory::query()->where('name', 'Frames')->count());
        $this->assertSoftDeleted(ItemCategory::withTrashed()->where('name', 'Archived')->sole());

        $migration->down();

        $this->assertSame($frames->id, $oldLensItem->fresh()->item_category_id);
        $this->assertSame($different->id, $oldFrameItem->fresh()->item_category_id);
        $this->assertSame($frames->id, $invoice->fresh()->invoice_category_id);
    }
}
