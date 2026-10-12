<?php

namespace Tests\Unit;

use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContainerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given a container at $120 per CBM (a note only) with two purchase invoices, each with its own price of one CBM:
     *     the first at $120 has a line of 10 cartons of 0.05 CBM (0.5 CBM) with 20 packets of 12, 2 per carton (240 pieces),
     *     the second at $100 has a line of 3 cartons of 0.5 CBM (1.5 CBM) with 6 packets of 20, 2 per carton (120 pieces),
     *     and a third purchase invoice at $50 outside any container, with a line of 4 cartons of 1 CBM (4 CBM).
     *     Then the container is 2 CBM and its transport costs $210, the sum of its invoices' ($60 + $150), not 2 × $120 = $240.
     *     The first invoice and its line cost $60, which is $0.25 per piece.
     *     The second line costs $150, which is $1.25 per piece.
     *     The invoice outside any container costs $200 at its own price, and is not counted in the container.
     * AR: حاوية سعر المتر بيها 120 $ (للملاحظة بس) وبيها فاتورتين شراء، كل وحدة بسعر متر مالها:
     *     الأولى بـ 120 $ بيها بند 10 كراتين حجم الكارتونة 0.05 CBM (يعني 0.5 CBM)، و20 باكيت × 12، 2 بالكارتونة (يعني 240 قطعة)،
     *     والثانية بـ 100 $ بيها بند 3 كراتين حجم الكارتونة 0.5 CBM (يعني 1.5 CBM)، و6 باكيت × 20، 2 بالكارتونة (يعني 120 قطعة)،
     *     وفاتورة شراء ثالثة بـ 50 $ مو بأي حاوية، بيها بند 4 كراتين حجم الكارتونة 1 CBM (يعني 4 CBM).
     *     فالحاوية 2 CBM ونقلها 210 $، مجموع نقل فواتيرها (60 $ + 150 $)، مو 2 × 120 $ = 240 $.
     *     الفاتورة الأولى وبندها نقلهم 60 $، يعني 0.25 $ للقطعة.
     *     البند الثاني نقله 150 $، يعني 1.25 $ للقطعة.
     *     الفاتورة اللي مو بحاوية نقلها 200 $ من سعرها هي، وما تنحسب بالحاوية.
     */
    public function test_transport_cost_of_a_container_its_invoices_and_each_piece(): void
    {
        $container = Container::factory()->create(['cbm_price' => 120]);

        $firstLine = $this->addLine(
            Invoice::factory()->purchase()->create(['container_id' => $container->id, 'cbm_price' => 120]),
            boxesCount: 20, boxesPerCarton: 2, cartonCbm: 0.05, boxQuantity: 12,
        );
        $secondLine = $this->addLine(
            Invoice::factory()->purchase()->create(['container_id' => $container->id, 'cbm_price' => 100]),
            boxesCount: 6, boxesPerCarton: 2, cartonCbm: 0.5, boxQuantity: 20,
        );
        $lineOutsideAnyContainer = $this->addLine(
            Invoice::factory()->purchase()->create(['cbm_price' => 50]),
            boxesCount: 4, boxesPerCarton: 1, cartonCbm: 1, boxQuantity: 10,
        );

        $this->assertEqualsWithDelta(2, $container->fresh()->totalCbm(), 0.0001);
        $this->assertEqualsWithDelta(210, $container->fresh()->transportCost(), 0.0001);

        $this->assertEqualsWithDelta(60, $firstLine->invoice->fresh()->transport_cost_usd, 0.0001);
        $this->assertEqualsWithDelta(60, $firstLine->fresh()->transport_cost_usd, 0.0001);
        $this->assertEqualsWithDelta(0.25, $firstLine->fresh()->transport_cost_per_piece_usd, 0.0001);

        $this->assertEqualsWithDelta(150, $secondLine->fresh()->transport_cost_usd, 0.0001);
        $this->assertEqualsWithDelta(1.25, $secondLine->fresh()->transport_cost_per_piece_usd, 0.0001);

        $this->assertEqualsWithDelta(200, $lineOutsideAnyContainer->invoice->fresh()->transport_cost_usd, 0.0001);
    }

    /**
     * EN: A container that still has invoices cannot be deleted; it stays as it is.
     * AR: الحاوية اللي بيها فواتير ما تنحذف، وتبقى مثل ما هي.
     */
    public function test_a_container_with_invoices_cannot_be_deleted(): void
    {
        $container = Container::factory()->create();
        Invoice::factory()->purchase()->create(['container_id' => $container->id]);

        try {
            $container->delete();
            $this->fail('A container with invoices must not be deleted.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSoftDeleted($container);
    }

    /**
     * EN: Only a purchase invoice can be in a container: a general invoice with a container is rejected.
     * AR: بس فاتورة الشراء تنضاف لحاوية: الفاتورة العامة اللي بيها حاوية تنرفض.
     */
    public function test_a_general_invoice_cannot_be_in_a_container(): void
    {
        $container = Container::factory()->create();

        $this->expectException(ValidationException::class);

        Invoice::factory()->create(['container_id' => $container->id]);
    }

    /**
     * EN: Adds a purchase line for a new item with the given packets, packets per carton, CBM of one carton, and pieces per packet.
     * AR: يضيف بند شراء لمادة جديدة بعدد الباكيتات، وتعبئة الكارتونة، وحجم الكارتونة، وعدد القطع بالباكيت.
     */
    private function addLine(Invoice $invoice, float $boxesCount, float $boxesPerCarton, float $cartonCbm, float $boxQuantity): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => $boxesCount,
            'box_quantity' => $boxQuantity,
            'boxes_per_carton' => $boxesPerCarton,
            'carton_cbm' => $cartonCbm,
            'price' => 1000,
        ]);
    }
}
