<?php

namespace Tests\Unit;

use App\Enums\InvoiceStage;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceStageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: When a purchase invoice is created, then its stage is "In production".
     * AR: تنعمل فاتورة شراء، فمرحلتها تكون "قيد التصنيع".
     */
    public function test_new_purchase_invoice_starts_in_production(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $this->assertSame(InvoiceStage::InProduction, $invoice->fresh()->stage);
    }

    /**
     * EN: When a general (not purchase) invoice is created, then it has no stage.
     * AR: تنعمل فاتورة عامة (مو شراء)، فما إلها مرحلة.
     */
    public function test_general_invoice_has_no_stage(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertNull($invoice->fresh()->stage);
    }

    /**
     * EN: Given a purchase invoice "In production", when its stage is advanced, then it becomes "Manufactured".
     * AR: فاتورة شراء "قيد التصنيع"، تتقدم مرحلتها، فتصير "تم التصنيع".
     */
    public function test_advancing_moves_one_step_forward(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $invoice->advanceStage();

        $this->assertSame(InvoiceStage::Manufactured, $invoice->fresh()->stage);
    }

    /**
     * EN: Given a new purchase invoice, when its stage is advanced step by step, then it goes through
     *     Manufactured, Received at the warehouse, On the way, then Received, in this order.
     * AR: فاتورة شراء جديدة، تتقدم مرحلتها خطوة خطوة، فتمر بـ تم التصنيع، تم الاستلام من المخزن، في الطريق، ثم تم الاستلام، بهذا الترتيب.
     */
    public function test_stages_follow_their_order_up_to_received(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $stages = [];

        for ($step = 0; $step < 4; $step++) {
            $invoice->advanceStage();
            $stages[] = $invoice->fresh()->stage;
        }

        $this->assertSame([
            InvoiceStage::Manufactured,
            InvoiceStage::ReceivedAtWarehouse,
            InvoiceStage::OnTheWay,
            InvoiceStage::Received,
        ], $stages);
    }

    /**
     * EN: Given a purchase invoice at "Received" (the last stage), when its stage is advanced, then it is refused and the stage stays "Received".
     * AR: فاتورة شراء بمرحلة "تم الاستلام" (آخر مرحلة)، تتقدم مرحلتها، فينرفض والمرحلة تبقى "تم الاستلام".
     */
    public function test_last_stage_cannot_be_advanced(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        for ($step = 0; $step < 4; $step++) {
            $invoice->advanceStage();
        }

        try {
            $invoice->advanceStage();
            $this->fail('The last stage was advanced.');
        } catch (ValidationException) {
            $this->assertSame(InvoiceStage::Received, $invoice->fresh()->stage);
        }
    }
}
