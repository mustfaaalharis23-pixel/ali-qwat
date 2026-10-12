<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceNameTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given a supplier named Ali with no invoices, when a purchase invoice is created for him, then its name is "Ali 1", whatever name was passed.
     * AR: مورد اسمه علي ما عنده فواتير، تنعمل له فاتورة شراء، فاسمها يصير "علي 1" مهما كان الاسم المرسل.
     */
    public function test_first_purchase_invoice_of_a_supplier_is_named_after_him_with_1(): void
    {
        $supplier = Supplier::factory()->create(['name_ar' => 'علي']);

        $invoice = $this->createPurchaseInvoice($supplier, name: 'any name');

        $this->assertSame('علي 1', $invoice->name);
    }

    /**
     * EN: Given a supplier with two purchase invoices, when a third is created, then it is named with 3.
     * AR: مورد عنده فاتورتين شراء، تنعمل الثالثة، فاسمها ينتهي بـ 3.
     */
    public function test_counter_goes_up_with_each_purchase_invoice_of_the_supplier(): void
    {
        $supplier = Supplier::factory()->create(['name_ar' => 'علي']);
        $this->createPurchaseInvoice($supplier);
        $this->createPurchaseInvoice($supplier);

        $invoice = $this->createPurchaseInvoice($supplier);

        $this->assertSame('علي 3', $invoice->name);
    }

    /**
     * EN: Given supplier Ali with two purchase invoices, when the first purchase invoice of supplier Omar is created, then it is "Omar 1": each supplier has his own counter.
     * AR: المورد علي عنده فاتورتين، تنعمل أول فاتورة للمورد عمر، فاسمها "عمر 1": كل مورد عنده عداده.
     */
    public function test_each_supplier_has_his_own_counter(): void
    {
        $ali = Supplier::factory()->create(['name_ar' => 'علي']);
        $this->createPurchaseInvoice($ali);
        $this->createPurchaseInvoice($ali);
        $omar = Supplier::factory()->create(['name_ar' => 'عمر']);

        $invoice = $this->createPurchaseInvoice($omar);

        $this->assertSame('عمر 1', $invoice->name);
    }

    /**
     * EN: Given a supplier with two purchase invoices and the second is deleted, when a new one is created, then it is named with 3, so no number is used twice.
     * AR: مورد عنده فاتورتين والثانية انحذفت، تنعمل فاتورة جديدة، فاسمها ينتهي بـ 3، حتى ما يتكرر رقم.
     */
    public function test_deleted_invoices_still_count_so_numbers_do_not_repeat(): void
    {
        $supplier = Supplier::factory()->create(['name_ar' => 'علي']);
        $this->createPurchaseInvoice($supplier);
        $this->createPurchaseInvoice($supplier)->delete();

        $invoice = $this->createPurchaseInvoice($supplier);

        $this->assertSame('علي 3', $invoice->name);
    }

    /**
     * EN: Given a purchase invoice named "Ali 1", when it is edited, then its name stays "Ali 1".
     * AR: فاتورة شراء اسمها "علي 1"، تنعدل، فاسمها يبقى "علي 1".
     */
    public function test_editing_a_purchase_invoice_keeps_its_name(): void
    {
        $invoice = $this->createPurchaseInvoice(Supplier::factory()->create(['name_ar' => 'علي']));

        $invoice->update(['date' => today()->subDay()]);

        $this->assertSame('علي 1', $invoice->fresh()->name);
    }

    /**
     * EN: Given a general (not purchase) invoice named "Rent", when it is created, then it keeps the name it was given.
     * AR: فاتورة عامة (مو شراء) اسمها "إيجار"، تنعمل، فتبقى بالاسم اللي انكتب.
     */
    public function test_general_invoice_keeps_the_name_it_was_given(): void
    {
        $invoice = Invoice::factory()->create(['name' => 'إيجار']);

        $this->assertSame('إيجار', $invoice->fresh()->name);
    }

    /**
     * EN: Creates a purchase invoice for the given supplier, with the given name passed in.
     * AR: ينشئ فاتورة شراء للمورد المحدد، ويرسل الاسم المحدد.
     */
    private function createPurchaseInvoice(Supplier $supplier, string $name = 'typed name'): Invoice
    {
        return Invoice::factory()->purchase()->create([
            'supplier_id' => $supplier->id,
            'name' => $name,
        ])->fresh();
    }
}
