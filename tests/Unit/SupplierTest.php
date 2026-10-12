<?php

namespace Tests\Unit;

use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A supplier can be saved with only its Arabic name; the Chinese name, phone, and notes stay null.
     * AR: المورد ينحفظ بس بالاسم العربي، والاسم الصيني والهاتف والملاحظات يبقون null.
     */
    public function test_supplier_can_be_saved_with_only_the_arabic_name(): void
    {
        DB::table('suppliers')->insert([
            'name_ar' => 'شركة النور',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name_ar' => 'شركة النور',
            'name_zh' => null,
            'phone' => null,
            'notes' => null,
        ]);
    }

    /**
     * EN: A supplier cannot be saved in the database without an Arabic name.
     * AR: المورد ما ينحفظ بالداتابيس بدون اسم عربي.
     */
    public function test_supplier_cannot_be_saved_without_the_arabic_name(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23502]');

        DB::table('suppliers')->insert([
            'name_zh' => '光明公司',
        ]);
    }

    /**
     * EN: All the supplier's data is saved as entered: Arabic name, Chinese name, phone, and notes.
     * AR: كل بيانات المورد تنحفظ مثل ما انكتبت: الاسم العربي والصيني والهاتف والملاحظات.
     */
    public function test_supplier_data_is_saved_as_entered(): void
    {
        $supplier = Supplier::factory()->create([
            'name_ar' => 'شركة النور',
            'name_zh' => '光明公司',
            'phone' => '+86 138-0013-8000',
            'notes' => 'Ships every month.',
        ]);

        $supplier = $supplier->fresh();

        $this->assertSame('شركة النور', $supplier->name_ar);
        $this->assertSame('光明公司', $supplier->name_zh);
        $this->assertSame('+86 138-0013-8000', $supplier->phone);
        $this->assertSame('Ships every month.', $supplier->notes);
    }

    /**
     * EN: The phone is plain text, so a leading zero is kept.
     * AR: الهاتف نص عادي، فالصفر اللي بأوله يبقى.
     */
    public function test_phone_keeps_its_leading_zero(): void
    {
        $supplier = Supplier::factory()->create(['phone' => '07701234567']);

        $this->assertSame('07701234567', $supplier->fresh()->phone);
    }

    /**
     * EN: Long notes (more than 255 characters) are saved in full.
     * AR: الملاحظات الطويلة (أكثر من 255 حرف) تنحفظ كاملة.
     */
    public function test_long_notes_are_saved_in_full(): void
    {
        $notes = str_repeat('Long supplier note. ', 50);

        $supplier = Supplier::factory()->create(['notes' => $notes]);

        $this->assertSame($notes, $supplier->fresh()->notes);
    }

    /**
     * EN: Deleting a supplier is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف المورد يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_a_supplier_soft_deletes_it(): void
    {
        $supplier = Supplier::factory()->create();

        $supplier->delete();

        $this->assertSoftDeleted($supplier);
    }
}
