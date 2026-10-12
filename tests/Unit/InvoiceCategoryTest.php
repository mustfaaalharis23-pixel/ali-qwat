<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceCategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A category that still has purchase invoices, such as Frames with one invoice, cannot be deleted; it stays as it is.
     * AR: الكتكري اللي بيها فواتير شراء، مثل "إطارات" وبيها فاتورة وحدة، ما تنحذف، وتبقى مثل ما هي.
     */
    public function test_a_category_with_invoices_cannot_be_deleted(): void
    {
        $category = InvoiceCategory::factory()->create(['name' => 'إطارات']);
        Invoice::factory()->purchase()->create(['invoice_category_id' => $category->id]);

        try {
            $category->delete();
            $this->fail('A category with invoices must not be deleted.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSoftDeleted($category);
    }

    /**
     * EN: A category with no invoices is deleted normally.
     * AR: الكتكري اللي ما بيها فواتير تنحذف عادي.
     */
    public function test_a_category_without_invoices_can_be_deleted(): void
    {
        $category = InvoiceCategory::factory()->create();

        $category->delete();

        $this->assertSoftDeleted($category);
    }

    /**
     * EN: Only a purchase invoice has a category: a general invoice with a category is rejected.
     * AR: بس فاتورة الشراء إلها كتكري: الفاتورة العامة اللي بيها كتكري تنرفض.
     */
    public function test_a_general_invoice_cannot_have_a_category(): void
    {
        $category = InvoiceCategory::factory()->create();

        $this->expectException(ValidationException::class);

        Invoice::factory()->create(['invoice_category_id' => $category->id]);
    }
}
