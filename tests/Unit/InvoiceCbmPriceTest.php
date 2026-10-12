<?php

namespace Tests\Unit;

use App\Models\Container;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceCbmPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A purchase invoice cannot be saved without its price of one CBM.
     * AR: فاتورة الشراء ما تنحفظ بدون سعر المتر مالها.
     */
    public function test_a_purchase_invoice_needs_a_cbm_price(): void
    {
        $this->expectException(ValidationException::class);

        Invoice::factory()->purchase()->create(['cbm_price' => null]);
    }

    /**
     * EN: While the invoice is in a container, its price of one CBM cannot be changed; it stays $120.
     * AR: طول ما الفاتورة بحاوية، سعر المتر مالها ما يتعدل؛ يبقى 120 $.
     */
    public function test_cbm_price_cannot_be_changed_while_the_invoice_is_in_a_container(): void
    {
        $invoice = Invoice::factory()->purchase()->create([
            'container_id' => Container::factory()->create()->id,
            'cbm_price' => 120,
        ]);

        try {
            $invoice->update(['cbm_price' => 200]);
            $this->fail('The price of one CBM of an invoice in a container must not change.');
        } catch (ValidationException) {
            //
        }

        $this->assertEqualsWithDelta(120, $invoice->fresh()->cbm_price, 0.000001);
    }

    /**
     * EN: Once the invoice has been in a container, its price of one CBM stays locked for good, even after it is taken out; it stays $120.
     * AR: أول ما الفاتورة تدخل حاوية، سعر المتر مالها ينقفل للأبد، حتى لو طلعت منها؛ يبقى 120 $.
     */
    public function test_cbm_price_stays_locked_after_the_invoice_leaves_its_container(): void
    {
        $invoice = Invoice::factory()->purchase()->create([
            'container_id' => Container::factory()->create()->id,
            'cbm_price' => 120,
        ]);
        $invoice->forceFill(['container_id' => null])->save();

        try {
            $invoice->fresh()->update(['cbm_price' => 200]);
            $this->fail('The price of one CBM of an invoice that was in a container must not change.');
        } catch (ValidationException) {
            //
        }

        $this->assertEqualsWithDelta(120, $invoice->fresh()->cbm_price, 0.000001);
    }
}
