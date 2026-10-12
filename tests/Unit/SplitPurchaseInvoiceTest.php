<?php

namespace Tests\Unit;

use App\Enums\InvoiceStage;
use App\Enums\InvoiceStatus;
use App\Invoices\SplitPurchaseInvoice;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SplitPurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given a line of 100 packets of 12 pieces, 10 packets per carton (10 cartons), 0.2 CBM per carton, 1 per piece (1200).
     *     4 of its 10 cartons go to the container.
     *     Then the new part has 40 packets (480 pieces), 4 cartons, 0.8 CBM, and costs 480,
     *     and the original is left with 60 packets (720 pieces), 6 cartons, 1.2 CBM, and costs 720.
     * AR: بند 100 باكيت × 12 قطعة، 10 باكيت بالكارتونة (10 كراتين)، حجم الكارتونة 0.2 CBM، والقطعة بـ 1 (يعني 1200).
     *     4 كراتين من 10 تروح للحاوية.
     *     فالجزء الجديد بيه 40 باكيت (480 قطعة)، و4 كراتين، و0.8 CBM، ومبلغه 480،
     *     والأصلية تبقى بيها 60 باكيت (720 قطعة)، و6 كراتين، و1.2 CBM، ومبلغها 720.
     */
    public function test_the_cartons_are_split_between_the_new_part_and_the_original(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 12, boxesPerCarton: 10, cartonCbm: 0.2, unitCost: 1);

        $part = $this->split($invoice, [$line->id => 4]);

        $shippedLine = $part->items()->sole();
        $this->assertEquals(40, $shippedLine->boxes_count);
        $this->assertEquals(480, $shippedLine->quantity);
        $this->assertEquals(4, $shippedLine->cartons_count);
        $this->assertEqualsWithDelta(0.8, $shippedLine->cbm, 0.0001);
        $this->assertEqualsWithDelta(480, $shippedLine->price, 0.0001);
        $this->assertEqualsWithDelta(480, $part->amount, 0.0001);
        $this->assertEqualsWithDelta(0.8, $part->cbm, 0.0001);

        $line = $line->fresh();
        $this->assertEquals(60, $line->boxes_count);
        $this->assertEquals(720, $line->quantity);
        $this->assertEquals(6, $line->cartons_count);
        $this->assertEqualsWithDelta(1.2, $line->cbm, 0.0001);
        $this->assertEqualsWithDelta(720, $line->price, 0.0001);
        $this->assertEqualsWithDelta(720, $invoice->fresh()->amount, 0.0001);
        $this->assertEqualsWithDelta(1.2, $invoice->fresh()->cbm, 0.0001);
    }

    /**
     * EN: Given a line of 25 packets, 10 packets per carton: 3 cartons, the last one holds only 5 packets.
     *     2 cartons go to the container: 20 packets go, and the original keeps 5 packets in 1 carton.
     * AR: بند 25 باكيت، 10 باكيت بالكارتونة: 3 كراتين، والأخيرة بيها 5 باكيتات بس.
     *     كارتونتين يروحن للحاوية: يروح 20 باكيت، والأصلية تبقى بيها 5 باكيتات بكارتونة وحدة.
     */
    public function test_full_cartons_carry_the_packets_of_a_full_carton(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 25, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);

        $part = $this->split($invoice, [$line->id => 2]);

        $this->assertEquals(20, $part->items()->sole()->boxes_count);
        $this->assertEquals(5, $line->fresh()->boxes_count);
        $this->assertEquals(1, $line->fresh()->cartons_count);
    }

    /**
     * EN: Given two lines. All 3 cartons of the first (25 packets, the last carton partly filled) go, and none of the second.
     *     Then all 25 packets of the first line go to the new part, and the line leaves the original,
     *     so the original has only the second line and the new part only the first.
     * AR: بندين. كل كراتين البند الأول الثلاثة (25 باكيت، والكارتونة الأخيرة ناقصة) تروح، وولا وحدة من الثاني.
     *     فكل الـ 25 باكيت مال البند الأول تروح للجزء الجديد، والبند يطلع من الأصلية،
     *     فالأصلية يبقى بيها البند الثاني بس، والجزء الجديد بيه البند الأول بس.
     */
    public function test_a_line_shipped_whole_leaves_the_original(): void
    {
        $invoice = $this->purchaseInvoice();
        $shipped = $this->addLine($invoice, boxesCount: 25, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $kept = $this->addLine($invoice, boxesCount: 10, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);

        $part = $this->split($invoice, [$shipped->id => 3, $kept->id => 0]);

        $this->assertSoftDeleted($shipped);
        $this->assertEquals([$kept->id], $invoice->items()->pluck('id')->all());

        $shippedLine = $part->items()->sole();
        $this->assertSame($shipped->item_id, $shippedLine->item_id);
        $this->assertEquals(25, $shippedLine->boxes_count);
    }

    /**
     * EN: The new part is created in the container. It copies the original's supplier, date, status, stage, and images,
     *     it is linked to the original, and it is named after it: "<original name> - part 1", then part 2 for the next one.
     * AR: الجزء الجديد ينخلق بالحاوية. ياخذ من الأصلية المورد والتاريخ والحالة والمرحلة والصور،
     *     وينربط بيها، وينسمى على اسمها: "<اسم الأصلية> - جزء 1"، والجزء اللي بعده جزء 2.
     */
    public function test_the_new_part_copies_the_original_and_goes_into_the_container(): void
    {
        $invoice = $this->purchaseInvoice();
        $invoice->forceFill(['stage' => InvoiceStage::Manufactured, 'date' => '2026-09-01'])->save();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $container = Container::factory()->create();

        $part = $this->split($invoice, [$line->id => 2], $container);
        $secondPart = $this->split($invoice, [$line->id => 1], Container::factory()->create());

        $invoice = $invoice->fresh();
        $this->assertSame($container->id, $part->container_id);
        $this->assertSame($invoice->id, $part->split_from_invoice_id);
        $this->assertSame($invoice->supplier_id, $part->supplier_id);
        $this->assertSame('2026-09-01', $part->date->toDateString());
        $this->assertSame(InvoiceStatus::ImporterConfirmed, $part->status);
        $this->assertSame(InvoiceStage::Manufactured, $part->stage);
        $this->assertSame($invoice->images, $part->images);
        $this->assertSame(__('invoices.split_part_name', ['name' => $invoice->name, 'number' => 1]), $part->name);
        $this->assertSame(__('invoices.split_part_name', ['name' => $invoice->name, 'number' => 2]), $secondPart->name);
        $this->assertNull($invoice->container_id);
    }

    /**
     * EN: Given an invoice of 1000 paid 800: an initial payment of 300, then a payoff of 500. 6 of its 10 cartons go (600).
     *     Then the original (now 400) keeps 400 of the payments: the initial 300 and 100 of the 500.
     *     The other 400 go to the new part (600), which is left with 200 to pay.
     *     The payments still add up to 800, and the supplier still owes 200.
     * AR: فاتورة بـ 1000 مسدد منها 800: دفعة أولى 300، وبعدها تسديد 500. 6 كراتين من 10 تروح (600).
     *     فالأصلية (صارت 400) يبقى عليها 400 من التسديدات: الأولى 300، و100 من الـ 500.
     *     والـ 400 الباقية تروح للجزء الجديد (600)، فيبقى عليه 200.
     *     التسديدات مجموعها يبقى 800، والمورد يبقى إله 200.
     */
    public function test_the_payoffs_stay_on_the_original_and_the_extra_goes_to_the_new_part(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $this->payOn($invoice, 300, isInitialPayment: true);
        $this->payOn($invoice, 500);

        $part = $this->split($invoice, [$line->id => 6]);

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta(400, $invoice->paid_amount, 0.0001);
        $this->assertEqualsWithDelta(0, $invoice->remaining, 0.0001);
        $this->assertEqualsWithDelta(400, $part->paid_amount, 0.0001);
        $this->assertEqualsWithDelta(200, $part->remaining, 0.0001);
        $this->assertEqualsWithDelta(800, Payoff::query()->sum('amount'), 0.0001);
        $this->assertEqualsWithDelta(200, $invoice->supplier->totalDebt(), 0.0001);
    }

    /**
     * EN: Given an invoice of 1000 paid only by an initial payment of 800. 6 of its 10 cartons go.
     *     Then the initial payment is split: 400 stays on the original and 400 goes to the new part,
     *     and both halves stay initial payments, so neither can be deleted on its own.
     * AR: فاتورة بـ 1000 مسددة بدفعة أولى 800 بس. 6 كراتين من 10 تروح.
     *     فالدفعة الأولى تنقسم: 400 تبقى على الأصلية و400 تروح للجزء الجديد،
     *     والنصين يبقون دفعة أولى، فولا واحد منهم ينحذف لحاله.
     */
    public function test_the_initial_payment_is_split_when_it_is_more_than_the_original_is_left_with(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $this->payOn($invoice, 800, isInitialPayment: true);

        $part = $this->split($invoice, [$line->id => 6]);

        $originalPayoff = $invoice->payoffs()->sole();
        $partPayoff = $part->payoffs()->sole();
        $this->assertEqualsWithDelta(400, $originalPayoff->amount, 0.0001);
        $this->assertEqualsWithDelta(400, $partPayoff->amount, 0.0001);
        $this->assertTrue($originalPayoff->is_initial_payment);
        $this->assertTrue($partPayoff->is_initial_payment);
    }

    /**
     * EN: Splitting only moves goods between the two invoices: the item's incoming quantity stays 1000,
     *     and the shipped line has its own stock batch of 400.
     * AR: التقسيم بس ينقل البضاعة بين الفاتورتين: القادم مال المادة يبقى 1000،
     *     والبند المشحون إله دفعة مخزون (batch) خاصة بيه بـ 400.
     */
    public function test_the_incoming_quantity_of_the_item_does_not_change(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $this->assertEquals(1000, $line->item->fresh()->incoming_quantity);

        $part = $this->split($invoice, [$line->id => 4]);

        $this->assertEquals(1000, $line->item->fresh()->incoming_quantity);
        $this->assertEquals(400, $part->items()->sole()->batch->quantity);
    }

    /**
     * EN: The cartons that cannot be split off: none from every line, all of every line (that is the whole invoice), or more than a line has.
     * AR: الكراتين اللي ما تنفصل: ولا كارتونة من كل البنود، أو كل كراتين كل البنود (يعني الفاتورة كاملة)، أو أكثر من اللي بالبند.
     *
     * @return array<string, array{int}>
     */
    public static function refusedCartons(): array
    {
        return [
            'no cartons' => [0],
            'all the cartons' => [10],
            'more than the line has' => [11],
        ];
    }

    /**
     * EN: Such cartons are refused: no new invoice is created, and the original stays as it was.
     * AR: هيج كراتين تنرفض: ما تنخلق فاتورة جديدة، والأصلية تبقى مثل ما هي.
     */
    #[DataProvider('refusedCartons')]
    public function test_cartons_that_cannot_be_split_off_are_refused(int $cartons): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);

        $this->assertRefusedAndUnchanged($invoice, fn () => $this->split($invoice, [$line->id => $cartons]));
    }

    /**
     * EN: The invoices that cannot be split: a confirmed one (its goods are already in the warehouse),
     *     one that is already in a container, and a deleted one.
     * AR: الفواتير اللي ما تنقسم: المؤكدة (بضاعتها صارت بالمخزن)، واللي هي أصلاً بحاوية، والمحذوفة.
     *
     * @return array<string, array{string}>
     */
    public static function invoicesThatCannotBeSplit(): array
    {
        return [
            'confirmed' => ['confirmed'],
            'in a container' => ['in a container'],
            'deleted' => ['deleted'],
        ];
    }

    /**
     * EN: Splitting such an invoice is refused: no new invoice is created, and the original stays as it was.
     * AR: تقسيم هيج فاتورة ينرفض: ما تنخلق فاتورة جديدة، والأصلية تبقى مثل ما هي.
     */
    #[DataProvider('invoicesThatCannotBeSplit')]
    public function test_an_invoice_that_cannot_be_split_is_refused(string $case): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);

        match ($case) {
            'confirmed' => $invoice->forceFill(['status' => InvoiceStatus::Confirmed])->save(),
            'in a container' => $invoice->forceFill(['container_id' => Container::factory()->create()->id])->save(),
            'deleted' => $invoice->delete(),
        };

        $this->assertRefusedAndUnchanged($invoice, fn () => $this->split($invoice->fresh(), [$line->id => 4]));
    }

    /**
     * EN: Runs the split, expects it to be refused, and checks that nothing changed: one invoice, with its line as it was.
     * AR: يشغّل التقسيم، ويتوقع ينرفض، ويتأكد ما تغيّر شي: فاتورة وحدة، وبندها مثل ما جان.
     */
    private function assertRefusedAndUnchanged(Invoice $invoice, callable $split): void
    {
        $lineBefore = $invoice->items()->withTrashed()->sole()->only(['boxes_count', 'price', 'cbm']);

        try {
            $split();
            $this->fail('The split must be refused.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(1, Invoice::withTrashed()->count());
        $this->assertEquals($lineBefore, $invoice->items()->withTrashed()->sole()->only(['boxes_count', 'price', 'cbm']));
    }

    /**
     * EN: Splits the given cartons of each line off the invoice into a container, and returns the new part.
     * AR: يفصل الكراتين المحددة لكل بند من الفاتورة لحاوية، ويرجّع الجزء الجديد.
     *
     * @param  array<int, int>  $cartonsByLine
     */
    private function split(Invoice $invoice, array $cartonsByLine, ?Container $container = null): Invoice
    {
        return (new SplitPurchaseInvoice)
            ->handle($invoice, $container ?? Container::factory()->create(), $cartonsByLine)
            ->fresh();
    }

    /**
     * EN: Creates a purchase invoice confirmed by the importer, so its goods are incoming.
     * AR: ينشئ فاتورة شراء مؤكدة من المستورد، فبضاعتها قادمة.
     */
    private function purchaseInvoice(): Invoice
    {
        return Invoice::factory()->purchase()->importerConfirmed()->create();
    }

    /**
     * EN: Adds a purchase line of a new item: its price is its pieces × the unit cost.
     * AR: يضيف بند شراء لمادة جديدة: سعره عدد قطعه × كلفة القطعة.
     */
    private function addLine(Invoice $invoice, float $boxesCount, float $boxQuantity, float $boxesPerCarton, float $cartonCbm, float $unitCost): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => $boxesCount,
            'box_quantity' => $boxQuantity,
            'boxes_per_carton' => $boxesPerCarton,
            'carton_cbm' => $cartonCbm,
            'price' => $boxesCount * $boxQuantity * $unitCost,
        ]);
    }

    /**
     * EN: Pays the given amount on the invoice, as its initial payment or as a later payoff.
     * AR: يسدد المبلغ المحدد على الفاتورة، كدفعة أولى أو كتسديد بعدين.
     */
    private function payOn(Invoice $invoice, float $amount, bool $isInitialPayment = false): Payoff
    {
        return Payoff::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'date' => $invoice->date,
            'is_initial_payment' => $isInitialPayment,
        ]);
    }
}
