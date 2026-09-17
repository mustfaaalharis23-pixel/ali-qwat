<?php

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The required columns: name, type, general description, user, date, and images. The supplier is not required, because a general invoice has none.
     * AR: الأعمدة الإجبارية: الاسم، النوع، الوصف العام، اليوزر، التاريخ، والصور. المورد مو إجباري، لأن الفاتورة العامة ما بيها مورد.
     *
     * @return array<string, array{string}>
     */
    public static function requiredColumns(): array
    {
        return [
            'name' => ['name'],
            'type' => ['type'],
            'general_description_id' => ['general_description_id'],
            'user_id' => ['user_id'],
            'date' => ['date'],
            'images' => ['images'],
        ];
    }

    /**
     * EN: An invoice cannot be saved in the database without any of its required columns.
     * AR: الفاتورة ما تنحفظ بالداتابيس إذا ناقصها أي عمود إجباري.
     */
    #[DataProvider('requiredColumns')]
    public function test_invoice_cannot_be_saved_without_a_required_column(string $column): void
    {
        $row = $this->row();
        unset($row[$column]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23502]');

        DB::table('invoices')->insert($row);
    }

    /**
     * EN: A new invoice gets its default values: zero amounts, pending, zero profit difference, and no notes, confirmations, or original invoice.
     * AR: الفاتورة الجديدة تاخذ قيمها الافتراضية: مبالغ صفر، معلّقة، فرق ربحية صفر، وبدون ملاحظات ولا تأكيدات ولا فاتورة أصلية.
     */
    public function test_new_invoice_gets_its_default_values(): void
    {
        DB::table('invoices')->insert($this->row());

        $this->assertDatabaseHas('invoices', [
            'name' => 'Office rent',
            'amount' => 0,
            'paid_amount' => 0,
            'remaining' => 0,
            'status' => 'pending',
            'profit_difference' => 0,
            'notes' => null,
            'importer_checker_id' => null,
            'importer_confirmed_at' => null,
            'receiver_checker_id' => null,
            'receiver_confirmed_at' => null,
            'original_invoice_id' => null,
        ]);
    }

    /**
     * EN: The invoice data is read back with its types: the type and status as enums, the date as a date, and the images as a list.
     * AR: بيانات الفاتورة ترجع بأنواعها: النوع والحالة كـ enum، والتاريخ كتاريخ، والصور كقائمة.
     */
    public function test_invoice_data_is_read_with_its_types(): void
    {
        $invoice = Invoice::factory()->create([
            'type' => InvoiceType::Input,
            'date' => '2026-09-01',
            'images' => ['invoices/a.jpg', 'invoices/b.jpg'],
            'notes' => 'Paid in cash.',
        ])->fresh();

        $this->assertSame(InvoiceType::Input, $invoice->type);
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertSame('2026-09-01', $invoice->date->toDateString());
        $this->assertSame(['invoices/a.jpg', 'invoices/b.jpg'], $invoice->images);
        $this->assertSame('Paid in cash.', $invoice->notes);
    }

    /**
     * EN: An invoice under the purchases description is a purchase invoice; any other invoice is a general invoice.
     * AR: الفاتورة اللي تحت وصف الشراء فاتورة شراء، وغيرها فاتورة عامة.
     */
    public function test_invoice_under_the_purchases_description_is_a_purchase_invoice(): void
    {
        $this->assertTrue(Invoice::factory()->purchase()->create()->isPurchase());
        $this->assertFalse(Invoice::factory()->create()->isPurchase());
    }

    /**
     * EN: An invoice is saved when its type is the same as its general description's type.
     * AR: الفاتورة تنحفظ إذا نوعها (داخل/خارج) نفس نوع وصفها العام.
     */
    public function test_invoice_with_the_type_of_its_general_description_is_saved(): void
    {
        $invoice = Invoice::factory()->create([
            'type' => InvoiceType::Input,
            'general_description_id' => GeneralDescription::factory()->create(['type' => InvoiceType::Input])->id,
        ]);

        $this->assertModelExists($invoice);
    }

    /**
     * EN: An invoice cannot be created with a type different from its general description's type.
     * AR: الفاتورة ما تنشأ إذا نوعها يختلف عن نوع وصفها العام.
     */
    public function test_invoice_cannot_be_created_with_a_type_different_from_its_general_description(): void
    {
        $outputDescription = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);

        try {
            Invoice::factory()->create([
                'type' => InvoiceType::Input,
                'general_description_id' => $outputDescription->id,
            ]);
            $this->fail('An input invoice must not be saved under an output general description.');
        } catch (ValidationException) {
            //
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    /**
     * EN: An invoice's type cannot be changed to a type different from its general description's type.
     * AR: نوع الفاتورة ما يتغير لنوع يختلف عن نوع وصفها العام.
     */
    public function test_invoice_type_cannot_be_changed_away_from_its_general_description_type(): void
    {
        $invoice = Invoice::factory()->create([
            'type' => InvoiceType::Output,
            'general_description_id' => GeneralDescription::factory()->create(['type' => InvoiceType::Output])->id,
        ]);

        try {
            $invoice->update(['type' => InvoiceType::Input]);
            $this->fail('The invoice type must not be changed away from its general description type.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(InvoiceType::Output, $invoice->fresh()->type);
    }

    /**
     * EN: An invoice cannot be moved to a general description of a different type.
     * AR: الفاتورة ما تنتقل لوصف عام نوعه يختلف عن نوعها.
     */
    public function test_invoice_cannot_be_moved_to_a_general_description_of_a_different_type(): void
    {
        $invoice = Invoice::factory()->create([
            'type' => InvoiceType::Output,
            'general_description_id' => GeneralDescription::factory()->create(['type' => InvoiceType::Output])->id,
        ]);
        $inputDescription = GeneralDescription::factory()->create(['type' => InvoiceType::Input]);

        try {
            $invoice->update(['general_description_id' => $inputDescription->id]);
            $this->fail('The invoice must not be moved to a general description of a different type.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSame($inputDescription->id, $invoice->fresh()->general_description_id);
    }

    /**
     * EN: A purchase invoice cannot be moved to another general description, even one of the same type.
     * AR: فاتورة الشراء ما تنتقل لوصف عام ثاني، حتى لو نفس نوعها.
     */
    public function test_purchase_invoice_cannot_be_moved_to_another_general_description(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $anotherOutputDescription = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);

        try {
            $invoice->update(['general_description_id' => $anotherOutputDescription->id]);
            $this->fail('A purchase invoice must not be moved to another general description.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(GeneralDescription::purchases()->id, $invoice->fresh()->general_description_id);
    }

    /**
     * EN: An invoice can be saved in the database without a supplier.
     * AR: الفاتورة تنحفظ بالداتابيس بدون مورد.
     */
    public function test_invoice_can_be_saved_in_the_database_without_a_supplier(): void
    {
        DB::table('invoices')->insert($this->row());

        $this->assertDatabaseHas('invoices', [
            'name' => 'Office rent',
            'supplier_id' => null,
        ]);
    }

    /**
     * EN: A general invoice has no supplier.
     * AR: الفاتورة العامة ما بيها مورد.
     */
    public function test_general_invoice_has_no_supplier(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertNull($invoice->fresh()->supplier_id);
    }

    /**
     * EN: A general invoice with a supplier is rejected.
     * AR: الفاتورة العامة اللي بيها مورد تنرفض.
     */
    public function test_general_invoice_with_a_supplier_is_rejected(): void
    {
        $supplier = Supplier::factory()->create();

        try {
            Invoice::factory()->create(['supplier_id' => $supplier->id]);
            $this->fail('A general invoice with a supplier must be rejected.');
        } catch (ValidationException) {
            //
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    /**
     * EN: A general invoice cannot be given a supplier later.
     * AR: الفاتورة العامة ما ينضافلها مورد بعدين.
     */
    public function test_general_invoice_cannot_be_given_a_supplier_later(): void
    {
        $invoice = Invoice::factory()->create();
        $supplier = Supplier::factory()->create();

        try {
            $invoice->update(['supplier_id' => $supplier->id]);
            $this->fail('A general invoice must not be given a supplier.');
        } catch (ValidationException) {
            //
        }

        $this->assertNull($invoice->fresh()->supplier_id);
    }

    /**
     * EN: A purchase invoice without a supplier is rejected.
     * AR: فاتورة الشراء بدون مورد تنرفض.
     */
    public function test_purchase_invoice_without_a_supplier_is_rejected(): void
    {
        try {
            Invoice::factory()->purchase()->create(['supplier_id' => null]);
            $this->fail('A purchase invoice without a supplier must be rejected.');
        } catch (ValidationException) {
            //
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    /**
     * EN: A supplier that has purchase invoices cannot be force deleted.
     * AR: المورد اللي عليه فواتير شراء ما ينحذف نهائياً.
     */
    public function test_supplier_with_invoices_cannot_be_force_deleted(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23503]');

        $invoice->supplier->forceDelete();
    }

    /**
     * EN: Deleting an invoice is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف الفاتورة يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_an_invoice_soft_deletes_it(): void
    {
        $invoice = Invoice::factory()->create();

        $invoice->delete();

        $this->assertSoftDeleted($invoice);
    }

    /**
     * EN: A complete general invoice row with all the required columns and no supplier, to insert directly into the database.
     * AR: سطر فاتورة عامة كامل بكل الأعمدة الإجبارية وبدون مورد، حتى ينضاف مباشرة للداتابيس.
     *
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'name' => 'Office rent',
            'type' => 'output',
            'general_description_id' => GeneralDescription::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'date' => '2026-09-01',
            'images' => json_encode(['invoices/rent.jpg']),
        ];
    }
}
