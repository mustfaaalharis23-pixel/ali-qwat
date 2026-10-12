<?php

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use App\Invoices\MergeSplitPart;
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

class MergeSplitPartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given a line of 100 packets of 12 pieces, 10 packets per carton, 0.2 CBM per carton, 1 per piece (1200),
     *     split 4 of its 10 cartons into a part. The part is merged back.
     *     Then the original line is whole again: 100 packets (1200 pieces), 10 cartons, 2 CBM, and 1200,
     *     it is still the original's only line, and the original's amount is 1200 again.
     * AR: بند 100 باكيت × 12 قطعة، 10 باكيت بالكارتونة، حجم الكارتونة 0.2 CBM، والقطعة بـ 1 (يعني 1200)،
     *     انفصلت منه 4 كراتين من 10 لجزء. الجزء يرجع للأصلية.
     *     فالبند الأصلي يرجع كامل: 100 باكيت (1200 قطعة)، و10 كراتين، و2 CBM، و1200،
     *     ويبقى البند الوحيد بالأصلية، ومبلغ الأصلية يرجع 1200.
     */
    public function test_the_part_lines_go_back_into_the_matching_original_lines(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 12, boxesPerCarton: 10, cartonCbm: 0.2, unitCost: 1);
        $part = $this->split($invoice, [$line->id => 4]);

        $this->merge($part);

        $this->assertEquals([$line->id], $invoice->items()->pluck('id')->all());
        $line = $line->fresh();
        $this->assertEquals(100, $line->boxes_count);
        $this->assertEquals(1200, $line->quantity);
        $this->assertEquals(10, $line->cartons_count);
        $this->assertEqualsWithDelta(2, $line->cbm, 0.0001);
        $this->assertEqualsWithDelta(1200, $line->price, 0.0001);
        $this->assertEqualsWithDelta(1200, $invoice->fresh()->amount, 0.0001);
        $this->assertEqualsWithDelta(2, $invoice->fresh()->cbm, 0.0001);
    }

    /**
     * EN: Given two lines, and all 3 cartons of the first (25 packets) split off, so the line left the original.
     *     The part is merged back.
     *     Then the original has two lines again: the first item's line comes back with its 25 packets and its price, and the second is untouched.
     * AR: بندين، وكل كراتين البند الأول الثلاثة (25 باكيت) انفصلت، فالبند طلع من الأصلية.
     *     الجزء يرجع للأصلية.
     *     فالأصلية يرجع بيها بندين: بند المادة الأولى يرجع بـ 25 باكيت وسعره، والثاني ما يتغيّر.
     */
    public function test_a_line_shipped_whole_comes_back_to_the_original(): void
    {
        $invoice = $this->purchaseInvoice();
        $shipped = $this->addLine($invoice, boxesCount: 25, boxQuantity: 2, boxesPerCarton: 10, cartonCbm: 1, unitCost: 3);
        $kept = $this->addLine($invoice, boxesCount: 10, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $part = $this->split($invoice, [$shipped->id => 3, $kept->id => 0]);

        $this->merge($part);

        $lines = $invoice->items()->get();
        $this->assertCount(2, $lines);

        $cameBack = $lines->firstWhere('item_id', $shipped->item_id);
        $this->assertEquals(25, $cameBack->boxes_count);
        $this->assertEquals(50, $cameBack->quantity);
        $this->assertEqualsWithDelta(150, $cameBack->price, 0.0001);

        $this->assertEquals(10, $kept->fresh()->boxes_count);
        $this->assertEqualsWithDelta(160, $invoice->fresh()->amount, 0.0001);
    }

    /**
     * EN: Given a split part that was edited afterwards: a line of another item (5 packets of 4 pieces at 2, so 40) was added to it.
     *     The part is merged back.
     *     Then that line matches no line of the original, so it is added to the original as a new line,
     *     and the original's amount is its first 1000 plus the 40.
     * AR: جزء منفصل انعدل بعدين: انضاف عليه بند لمادة ثانية (5 باكيت × 4 قطع، القطعة بـ 2، يعني 40).
     *     الجزء يرجع للأصلية.
     *     فهذا البند ما يطابق ولا بند بالأصلية، فينضاف للأصلية كبند جديد،
     *     ومبلغ الأصلية يصير الـ 1000 الأولى + 40.
     */
    public function test_a_part_line_that_matches_no_original_line_is_added_as_a_new_line(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $part = $this->split($invoice, [$line->id => 4]);
        $added = $this->addLine($part, boxesCount: 5, boxQuantity: 4, boxesPerCarton: 5, cartonCbm: 1, unitCost: 2);

        $this->merge($part);

        $lines = $invoice->items()->get();
        $this->assertCount(2, $lines);
        $this->assertEquals(100, $line->fresh()->boxes_count);

        $newLine = $lines->firstWhere('item_id', $added->item_id);
        $this->assertEquals(5, $newLine->boxes_count);
        $this->assertEqualsWithDelta(40, $newLine->price, 0.0001);
        $this->assertEqualsWithDelta(1040, $invoice->fresh()->amount, 0.0001);
    }

    /**
     * EN: Given an invoice of 1000 paid 800 (an initial payment of 300, then a payoff of 500), and 6 of its 10 cartons split off:
     *     the original kept 400 of the payments and the part took the other 400.
     *     The part is merged back.
     *     Then all 800 are on the original again, 200 is left to pay, and the supplier still owes 200.
     *     The payments come back as they are, as separate rows: 300, 100, and 400.
     * AR: فاتورة بـ 1000 مسدد منها 800 (دفعة أولى 300، وبعدها تسديد 500)، وانفصلت 6 كراتين من 10:
     *     الأصلية بقى عليها 400 من التسديدات، والجزء اخذ الـ 400 الباقية.
     *     الجزء يرجع للأصلية.
     *     فكل الـ 800 يرجعون على الأصلية، ويبقى عليها 200، والمورد يبقى إله 200.
     *     التسديدات ترجع مثل ما هي، كسطور منفصلة: 300، و100، و400.
     */
    public function test_the_payoffs_go_back_to_the_original(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $this->payOn($invoice, 300, isInitialPayment: true);
        $this->payOn($invoice, 500);
        $part = $this->split($invoice, [$line->id => 6]);

        $this->merge($part);

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta(1000, $invoice->amount, 0.0001);
        $this->assertEqualsWithDelta(800, $invoice->paid_amount, 0.0001);
        $this->assertEqualsWithDelta(200, $invoice->remaining, 0.0001);
        $this->assertEqualsWithDelta(200, $invoice->supplier->totalDebt(), 0.0001);
        $this->assertEqualsWithDelta(800, Payoff::query()->sum('amount'), 0.0001);
        $this->assertEqualsCanonicalizing([300, 100, 400], $invoice->payoffs()->pluck('amount')->map(fn ($amount): float => (float) $amount)->all());
    }

    /**
     * EN: Given an invoice paid only by an initial payment of 800, split in two by the split: 400 on the original and 400 on the part.
     *     The part is merged back.
     *     Then the original has two initial payments of 400, both still initial payments, and nothing is left on the part.
     * AR: فاتورة مسددة بدفعة أولى 800 بس، انقسمت بالتقسيم: 400 على الأصلية و400 على الجزء.
     *     الجزء يرجع للأصلية.
     *     فالأصلية يصير بيها دفعتين أولى كل وحدة 400، والثنتين يبقن دفعة أولى، وما يبقى شي على الجزء.
     */
    public function test_a_split_initial_payment_comes_back_as_two_initial_payments(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $this->payOn($invoice, 800, isInitialPayment: true);
        $part = $this->split($invoice, [$line->id => 6]);

        $this->merge($part);

        $payoffs = $invoice->payoffs()->get();
        $this->assertCount(2, $payoffs);
        $this->assertTrue($payoffs->every(fn (Payoff $payoff): bool => $payoff->is_initial_payment));
        $this->assertEqualsWithDelta(800, $payoffs->sum('amount'), 0.0001);
        $this->assertSame(0, Payoff::withTrashed()->where('invoice_id', $part->id)->count());
    }

    /**
     * EN: Merging only moves goods back between the two invoices: the item's incoming quantity stays 1000,
     *     and the original line's stock batch is 1000 again.
     * AR: الدمج بس يرجّع البضاعة بين الفاتورتين: القادم مال المادة يبقى 1000،
     *     ودفعة المخزون (batch) مال البند الأصلي ترجع 1000.
     */
    public function test_the_incoming_quantity_of_the_item_does_not_change(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $part = $this->split($invoice, [$line->id => 4]);

        $this->merge($part);

        $this->assertEquals(1000, $line->item->fresh()->incoming_quantity);
        $this->assertEquals(1000, $line->fresh()->batch->quantity);
    }

    /**
     * EN: After the merge the part is removed for good, with its lines, so it cannot be restored from the trash and counted twice,
     *     and it leaves its container with it. The original stays in no container.
     * AR: بعد الدمج الجزء ينمسح نهائياً ويا بنوده، حتى ما ينرجع من سلة المحذوفات وينحسب مرتين،
     *     ويطلع من الحاوية وياه. والأصلية تبقى بدون حاوية.
     */
    public function test_the_part_is_removed_for_good_and_leaves_its_container(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $container = Container::factory()->create();
        $part = $this->split($invoice, [$line->id => 4], $container);

        $this->merge($part);

        $this->assertNull(Invoice::withTrashed()->find($part->id));
        $this->assertSame(0, InvoiceItem::withTrashed()->where('invoice_id', $part->id)->count());
        $this->assertSame(0, $container->invoices()->withTrashed()->count());
        $this->assertNull($invoice->fresh()->container_id);
    }

    /**
     * EN: The merges that are refused: an invoice that was not split off another, a confirmed original or part
     *     (its goods are already in the warehouse), an original that is now in a container (its goods would move into that container),
     *     a deleted original or part, and a part whose status differs from the original's (the goods would change their confirmation).
     * AR: الدمج اللي ينرفض: فاتورة ما انفصلت من فاتورة ثانية، أصلية أو جزء مؤكد (بضاعته صارت بالمخزن)،
     *     أصلية صارت بحاوية (بضاعة الجزء تنتقل لذيج الحاوية)، أصلية أو جزء محذوف،
     *     وجزء حالته غير حالة الأصلية (البضاعة يتغيّر تأكيدها).
     *
     * @return array<string, array{string}>
     */
    public static function refusedMerges(): array
    {
        return [
            'not a split part' => ['not a split part'],
            'original confirmed' => ['original confirmed'],
            'part confirmed' => ['part confirmed'],
            'original in a container' => ['original in a container'],
            'original deleted' => ['original deleted'],
            'part deleted' => ['part deleted'],
            'different statuses' => ['different statuses'],
        ];
    }

    /**
     * EN: Such a merge is refused, and both invoices stay as they were: same lines, same amounts, and the part still exists.
     * AR: هيج دمج ينرفض، والفاتورتين يبقن مثل ما هن: نفس البنود، ونفس المبالغ، والجزء بعده موجود.
     */
    #[DataProvider('refusedMerges')]
    public function test_a_merge_that_cannot_be_made_is_refused(string $case): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $part = $this->split($invoice, [$line->id => 4]);

        $target = match ($case) {
            'not a split part' => $invoice,
            default => $part,
        };

        match ($case) {
            'original confirmed' => $invoice->forceFill(['status' => InvoiceStatus::Confirmed])->save(),
            'part confirmed', 'different statuses' => $part->forceFill([
                'status' => $case === 'part confirmed' ? InvoiceStatus::Confirmed : InvoiceStatus::Pending,
            ])->save(),
            'original in a container' => $invoice->forceFill(['container_id' => Container::factory()->create()->id])->save(),
            'original deleted' => $invoice->delete(),
            'part deleted' => $part->delete(),
            default => null,
        };

        $before = $this->snapshot($invoice, $part);

        try {
            $this->merge($target->fresh());
            $this->fail('The merge must be refused.');
        } catch (ValidationException) {
            //
        }

        $this->assertEquals($before, $this->snapshot($invoice, $part));
    }

    /**
     * EN: The lines and amounts of both invoices, and whether the part still exists, to compare before and after a refused merge.
     * AR: بنود ومبالغ الفاتورتين، وهل الجزء بعده موجود، حتى نقارن قبل وبعد الدمج المرفوض.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Invoice $invoice, Invoice $part): array
    {
        $linesOf = fn (Invoice $of): array => InvoiceItem::withTrashed()
            ->where('invoice_id', $of->id)
            ->orderBy('id')
            ->get()
            ->map(fn (InvoiceItem $line): array => $line->only(['id', 'boxes_count', 'price', 'deleted_at']))
            ->all();

        return [
            'original lines' => $linesOf($invoice),
            'original amount' => (float) Invoice::withTrashed()->find($invoice->id)?->amount,
            'part lines' => $linesOf($part),
            'part amount' => (float) Invoice::withTrashed()->find($part->id)?->amount,
            'part exists' => Invoice::withTrashed()->whereKey($part->id)->exists(),
        ];
    }

    /**
     * EN: Merges the split part back into its original, and returns the original.
     * AR: يرجّع الجزء المنفصل لفاتورته الأصلية، ويرجّع الأصلية.
     */
    private function merge(Invoice $part): Invoice
    {
        return (new MergeSplitPart)->handle($part);
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
