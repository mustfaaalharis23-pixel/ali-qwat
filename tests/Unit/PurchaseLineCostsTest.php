<?php

namespace Tests\Unit;

use App\Invoices\SplitPurchaseInvoice;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseLineCostsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A purchase line saves its costs in dollars through its invoice's exchange rate: with 1 $ = 7 yuan,
     *     a line of 100 pieces at 3.5 yuan (350 yuan) keeps 0.5 $ per piece and 50 $ for the line.
     * AR: بند الشراء يحفظ كلفه بالدولار من سعر صرف فاتورته: إذا 1 $ = 7 يوان،
     *     بند 100 قطعة بـ 3.5 يوان (350 يوان) يحفظ 0.5 $ للقطعة و50 $ للبند.
     */
    public function test_line_saves_its_costs_in_dollars_through_the_exchange_rate(): void
    {
        $line = $this->addLine($this->invoice(exchangeRate: 7), pieces: 100, unitCost: 3.5);

        $this->assertEqualsWithDelta(3.5, $line->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(0.5, $line->unit_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(50, $line->price_usd, 0.000001);
    }

    /**
     * EN: A line saved without its unit cost in yuan gets it from its price ÷ its pieces, and its dollar costs from that.
     * AR: البند اللي ينحفظ بدون كلفة القطعة باليوان، تنحسبله من سعره ÷ عدد قطعه، وكلفه بالدولار منها.
     */
    public function test_line_without_a_unit_cost_gets_it_from_its_price(): void
    {
        $line = $this->addLine($this->invoice(exchangeRate: 7), pieces: 100, price: 350);

        $this->assertEqualsWithDelta(3.5, $line->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(0.5, $line->unit_cost_usd, 0.000001);
    }

    /**
     * EN: Editing a line saves its dollar costs again.
     * AR: تعديل البند يحفظ كلفه بالدولار من جديد.
     */
    public function test_editing_a_line_saves_its_dollar_costs_again(): void
    {
        $line = $this->addLine($this->invoice(exchangeRate: 7), pieces: 100, unitCost: 3.5);

        $line->update(['unit_cost' => 7, 'price' => 700]);

        $this->assertEqualsWithDelta(1, $line->fresh()->unit_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(100, $line->fresh()->price_usd, 0.000001);
    }

    /**
     * EN: A line of an invoice without an exchange rate has no dollar costs.
     * AR: بند فاتورة ما بيها سعر صرف ما إله كلف بالدولار.
     */
    public function test_line_of_an_invoice_without_an_exchange_rate_has_no_dollar_costs(): void
    {
        $line = $this->addLine($this->invoice(exchangeRate: null), pieces: 100, unitCost: 3.5);

        $this->assertNull($line->unit_cost_usd);
        $this->assertNull($line->price_usd);
    }

    /**
     * EN: The invoice saves its amount in dollars: the sum of its lines' prices in dollars, again when a line is deleted.
     * AR: الفاتورة تحفظ مبلغها بالدولار: مجموع أسعار بنودها بالدولار، ومن جديد إذا انحذف بند.
     */
    public function test_invoice_saves_its_amount_in_dollars_as_the_sum_of_its_lines(): void
    {
        $invoice = $this->invoice(exchangeRate: 7);
        $this->addLine($invoice, pieces: 100, unitCost: 3.5);
        $second = $this->addLine($invoice, pieces: 10, unitCost: 14);

        $this->assertEqualsWithDelta(70, $invoice->fresh()->amount_usd, 0.000001);

        $second->delete();

        $this->assertEqualsWithDelta(50, $invoice->fresh()->amount_usd, 0.000001);
    }

    /**
     * EN: Each line saves its transport cost in dollars from its own invoice's price of one CBM (its CBM × that price),
     *     and the transport of one piece (the line's ÷ its pieces), and the invoice saves the sum of its lines', even outside any container.
     *     At $120 per CBM: a line of 10 cartons of 0.05 CBM (0.5 CBM) and 240 pieces costs $60, $0.25 per piece,
     *     and a line of 3 cartons of 0.5 CBM (1.5 CBM) and 120 pieces costs $180, $1.50 per piece. The invoice costs $240.
     * AR: كل بند يحفظ كلفة نقله بالدولار من سعر المتر المكتوب بفاتورته هي (CBM البند × هذا السعر)،
     *     ونقل القطعة الوحدة (نقل البند ÷ عدد قطعه)، والفاتورة تحفظ مجموع نقل بنودها، حتى لو مو بأي حاوية.
     *     بسعر 120 $ للمتر: بند 10 كراتين حجم الكارتونة 0.05 CBM (يعني 0.5 CBM) و240 قطعة نقله 60 $، يعني 0.25 $ للقطعة،
     *     وبند 3 كراتين حجم الكارتونة 0.5 CBM (يعني 1.5 CBM) و120 قطعة نقله 180 $، يعني 1.50 $ للقطعة. والفاتورة نقلها 240 $.
     */
    public function test_lines_save_their_transport_costs_from_their_invoice_cbm_price(): void
    {
        $invoice = $this->invoice(exchangeRate: 7, cbmPrice: 120);
        $first = $this->addLine($invoice, pieces: 240, unitCost: 1, cartons: 10, cartonCbm: 0.05);
        $second = $this->addLine($invoice, pieces: 120, unitCost: 1, cartons: 3, cartonCbm: 0.5);

        $this->assertEqualsWithDelta(60, $first->fresh()->transport_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(0.25, $first->fresh()->transport_cost_per_piece_usd, 0.000001);
        $this->assertEqualsWithDelta(180, $second->fresh()->transport_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(1.5, $second->fresh()->transport_cost_per_piece_usd, 0.000001);
        $this->assertEqualsWithDelta(240, $invoice->fresh()->transport_cost_usd, 0.000001);
    }

    /**
     * EN: An invoice in a container keeps its own price of one CBM: at $120 on the invoice in a container at $200, its 0.5 CBM line costs $60, not $100,
     *     and changing the container's price later changes nothing, because the container's price is a note only.
     * AR: الفاتورة اللي بحاوية تبقى على سعر المتر مالها: فاتورة بـ 120 $ بحاوية سعرها 200 $، بندها 0.5 CBM نقله 60 $ مو 100 $،
     *     وتغيير سعر الحاوية بعدين ما يغيّر شي، لأن سعر الحاوية للملاحظة بس.
     */
    public function test_invoice_in_a_container_keeps_its_own_cbm_price(): void
    {
        $container = Container::factory()->create(['cbm_price' => 200]);
        $invoice = $this->invoice(exchangeRate: 7, container: $container, cbmPrice: 120);
        $line = $this->addLine($invoice, pieces: 240, unitCost: 1, cartons: 10, cartonCbm: 0.05);

        $container->update(['cbm_price' => 300]);

        $this->assertEqualsWithDelta(120, $invoice->fresh()->cbm_price, 0.000001);
        $this->assertEqualsWithDelta(60, $line->fresh()->transport_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(60, $invoice->fresh()->transport_cost_usd, 0.000001);
    }

    /**
     * EN: Changing the invoice's price of one CBM before it enters a container saves the transport costs again:
     *     from $120 to $200, the 0.5 CBM line of 240 pieces goes from $60 to $100, about $0.4167 per piece, and the invoice to $100.
     * AR: تغيير سعر المتر بالفاتورة قبل لا تدخل حاوية يحفظ كلف النقل من جديد:
     *     من 120 $ لـ 200 $، البند 0.5 CBM و240 قطعة يصير نقله 100 $ بدل 60 $، تقريباً 0.4167 $ للقطعة، والفاتورة 100 $.
     */
    public function test_changing_the_cbm_price_saves_the_transport_costs_again(): void
    {
        $invoice = $this->invoice(exchangeRate: 7, cbmPrice: 120);
        $line = $this->addLine($invoice, pieces: 240, unitCost: 1, cartons: 10, cartonCbm: 0.05);

        $invoice->fresh()->update(['cbm_price' => 200]);

        $this->assertEqualsWithDelta(100, $line->fresh()->transport_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(100 / 240, $line->fresh()->transport_cost_per_piece_usd, 0.000001);
        $this->assertEqualsWithDelta(100, $invoice->fresh()->transport_cost_usd, 0.000001);
    }

    /**
     * EN: Taking an invoice out of its container keeps the transport costs of its lines and of the invoice, because they come from the invoice's own price.
     * AR: إطلاع الفاتورة من حاويتها ما يمسح كلف النقل من بنودها ولا من الفاتورة، لأنها من سعر الفاتورة نفسها.
     */
    public function test_taking_an_invoice_out_of_its_container_keeps_its_transport_costs(): void
    {
        $invoice = $this->invoice(exchangeRate: 7, container: Container::factory()->create(['cbm_price' => 200]), cbmPrice: 120);
        $line = $this->addLine($invoice, pieces: 240, unitCost: 1, cartons: 10, cartonCbm: 0.05);

        $invoice->fresh()->forceFill(['container_id' => null])->save();

        $this->assertEqualsWithDelta(60, $line->fresh()->transport_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(0.25, $line->fresh()->transport_cost_per_piece_usd, 0.000001);
        $this->assertEqualsWithDelta(60, $invoice->fresh()->transport_cost_usd, 0.000001);
    }

    /**
     * EN: The part of an invoice split into a container keeps the unit cost of the original line, its costs in dollars,
     *     and the original's price of one CBM, and its transport cost comes from that price, not the container's.
     * AR: الجزء اللي ينقسم من فاتورة لحاوية ياخذ كلفة القطعة من البند الأصلي، وكلفه بالدولار،
     *     وسعر المتر مال الفاتورة الأصلية، وكلفة نقله تنحسب من هذا السعر مو من سعر الحاوية.
     */
    public function test_split_part_keeps_the_unit_cost_and_the_cbm_price(): void
    {
        $invoice = $this->invoice(exchangeRate: 7, cbmPrice: 120);
        $line = $this->addLine($invoice, pieces: 240, unitCost: 3.5, cartons: 10, cartonCbm: 0.05);
        $container = Container::factory()->create(['cbm_price' => 200]);

        $part = (new SplitPurchaseInvoice)->handle($invoice, $container, [$line->id => 4]);
        $partLine = $part->items()->sole();

        $this->assertEqualsWithDelta(120, $part->fresh()->cbm_price, 0.000001);
        $this->assertEqualsWithDelta(3.5, $partLine->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(0.5, $partLine->unit_cost_usd, 0.000001);
        // 4 cartons of 0.05 CBM = 0.2 CBM × $120 = $24, over 96 pieces.
        $this->assertEqualsWithDelta(24, $partLine->transport_cost_usd, 0.000001);
        $this->assertEqualsWithDelta(0.25, $partLine->transport_cost_per_piece_usd, 0.000001);
    }

    /**
     * EN: A line saves its costs in Iraqi dinars through its invoice's dinar rate, written as the market writes it:
     *     170 means 170,000 dinars for 100 dollars, so one dollar is 1,700 dinars. With 1 $ = 7 yuan and $120 per CBM,
     *     a line of 240 pieces at 3.5 yuan in 10 cartons of 0.05 CBM (0.5 CBM) saves:
     *     850 dinars a piece (0.5 $), 204,000 dinars for the line (120 $), 102,000 dinars transport (60 $),
     *     425 dinars transport a piece (0.25 $), and 1,275 dinars as the final cost of a piece (850 + 425).
     * AR: البند يحفظ كلفه بالدينار العراقي من سعر الدينار بفاتورته، مكتوب مثل ما يحچي السوق:
     *     170 يعني 100 $ = 170 ألف دينار، يعني الدولار = 1,700 دينار. إذا 1 $ = 7 يوان والمتر بـ 120 $،
     *     بند 240 قطعة بـ 3.5 يوان بـ 10 كراتين حجم الكارتونة 0.05 CBM (يعني 0.5 CBM) يحفظ:
     *     القطعة 850 دينار (0.5 $)، والبند 204,000 دينار (120 $)، والنقل 102,000 دينار (60 $)،
     *     ونقل القطعة 425 دينار (0.25 $)، والكلفة النهائية للقطعة 1,275 دينار (850 + 425).
     */
    public function test_line_saves_its_costs_in_dinars(): void
    {
        $invoice = $this->invoice(exchangeRate: 7, cbmPrice: 120, dinarRate: 170);
        $line = $this->addLine($invoice, pieces: 240, unitCost: 3.5, cartons: 10, cartonCbm: 0.05);

        $this->assertEqualsWithDelta(850, $line->unit_cost_iqd, 0.0001);
        $this->assertEqualsWithDelta(204000, $line->price_iqd, 0.0001);
        $this->assertEqualsWithDelta(102000, $line->transport_cost_iqd, 0.0001);
        $this->assertEqualsWithDelta(425, $line->transport_cost_per_piece_iqd, 0.0001);
        $this->assertEqualsWithDelta(1275, $line->final_unit_cost_iqd, 0.0001);
    }

    /**
     * EN: The invoice saves its amount and its transport cost in dinars, each the sum of its lines':
     *     with the line above and a line of 120 pieces at 14 yuan in 3 cartons of 0.5 CBM (408,000 dinars, 306,000 dinars transport),
     *     the invoice is 612,000 dinars and its transport 408,000 dinars.
     * AR: الفاتورة تحفظ مبلغها وكلفة نقلها بالدينار، كل وحدة مجموع بنودها:
     *     ويّا البند اللي فوك وبند 120 قطعة بـ 14 يوان بـ 3 كراتين حجم الكارتونة 0.5 CBM (408,000 دينار، ونقله 306,000 دينار)،
     *     الفاتورة 612,000 دينار ونقلها 408,000 دينار.
     */
    public function test_invoice_saves_its_amount_and_transport_in_dinars(): void
    {
        $invoice = $this->invoice(exchangeRate: 7, cbmPrice: 120, dinarRate: 170);
        $this->addLine($invoice, pieces: 240, unitCost: 3.5, cartons: 10, cartonCbm: 0.05);
        $this->addLine($invoice, pieces: 120, unitCost: 14, cartons: 3, cartonCbm: 0.5);

        $this->assertEqualsWithDelta(612000, $invoice->fresh()->amount_iqd, 0.0001);
        $this->assertEqualsWithDelta(408000, $invoice->fresh()->transport_cost_iqd, 0.0001);
    }

    /**
     * EN: The item's cost is for a dozen: the final dinar cost of a piece on its confirmed purchase × 12, so 1,275 × 12 = 15,300 dinars.
     *     Changing the invoice's dinar rate from 170 to 160 saves the costs again: a piece is 800 + 400 = 1,200 dinars, a dozen 14,400.
     * AR: كلفة المادة للدرزن: الكلفة النهائية للقطعة بالدينار بفاتورة شرائها المؤكدة × 12، يعني 1,275 × 12 = 15,300 دينار.
     *     تغيير سعر الدينار بالفاتورة من 170 لـ 160 يحفظ الكلف من جديد: القطعة تصير 800 + 400 = 1,200 دينار، والدرزن 14,400.
     */
    public function test_item_cost_is_the_final_dinar_cost_and_follows_the_dinar_rate(): void
    {
        $invoice = Invoice::factory()->purchase()->confirmed()->create(['exchange_rate' => 7, 'cbm_price' => 120, 'dinar_rate' => 170]);
        $line = $this->addLine($invoice, pieces: 240, unitCost: 3.5, cartons: 10, cartonCbm: 0.05);

        $this->assertEqualsWithDelta(15300, $line->item->fresh()->cost_price, 0.0001);

        $invoice->fresh()->update(['dinar_rate' => 160]);

        $this->assertEqualsWithDelta(1200, $line->fresh()->final_unit_cost_iqd, 0.0001);
        $this->assertEqualsWithDelta(14400, $line->item->fresh()->cost_price, 0.0001);
    }

    /**
     * EN: The part of an invoice split into a container keeps the original's dinar rate, so its piece costs the same 1,275 dinars.
     * AR: الجزء اللي ينقسم من فاتورة لحاوية ياخذ سعر الدينار من الفاتورة الأصلية، فقطعته تبقى بنفس الـ 1,275 دينار.
     */
    public function test_split_part_keeps_the_dinar_rate(): void
    {
        $invoice = $this->invoice(exchangeRate: 7, cbmPrice: 120, dinarRate: 170);
        $line = $this->addLine($invoice, pieces: 240, unitCost: 3.5, cartons: 10, cartonCbm: 0.05);

        $part = (new SplitPurchaseInvoice)->handle($invoice, Container::factory()->create(), [$line->id => 4]);

        $this->assertEqualsWithDelta(170, $part->fresh()->dinar_rate, 0.0001);
        $this->assertEqualsWithDelta(1275, $part->items()->sole()->final_unit_cost_iqd, 0.0001);
    }

    private function invoice(?float $exchangeRate, ?Container $container = null, float $cbmPrice = 120, ?float $dinarRate = null): Invoice
    {
        return Invoice::factory()->purchase()->create([
            'exchange_rate' => $exchangeRate,
            'container_id' => $container?->id,
            'cbm_price' => $cbmPrice,
            'dinar_rate' => $dinarRate,
        ]);
    }

    /**
     * A line of the given pieces, as one packet in each of the given cartons, each carton of the given CBM.
     * It is saved with its unit cost of a piece in yuan and its price (pieces × unit cost), or with only a price.
     */
    private function addLine(
        Invoice $invoice,
        float $pieces,
        ?float $unitCost = null,
        ?float $price = null,
        float $cartons = 1,
        float $cartonCbm = 0.1,
    ): InvoiceItem {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => $cartons,
            'pieces_per_packet' => $pieces / $cartons,
            'boxes_per_carton' => 1,
            'carton_cbm' => $cartonCbm,
            'unit_cost' => $unitCost,
            'price' => $price ?? $pieces * $unitCost,
        ])->fresh();
    }
}
