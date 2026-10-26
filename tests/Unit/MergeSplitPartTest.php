<?php

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use App\Invoices\MergeSplitPart;
use App\Invoices\SplitPurchaseInvoice;
use App\Models\Color;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
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
     * EN: Merging only moves goods back between the two invoices: given 100 packets of 12 pieces (1200 pieces),
     *     the item's incoming quantity stays 100 dozens, and the original line's stock batch is 1200 pieces again.
     * AR: الدمج بس يرجّع البضاعة بين الفاتورتين: 100 باكيت، كل باكيت 12 قطعة (1200 قطعة)،
     *     فالقادم مال المادة يبقى 100 درزن، ودفعة المخزون (batch) مال البند الأصلي ترجع 1200 قطعة.
     */
    public function test_the_incoming_quantity_of_the_item_does_not_change(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 12, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $part = $this->split($invoice, [$line->id => 4]);

        $this->merge($part);

        $this->assertEquals(100, $line->item->fresh()->incoming_quantity);
        $this->assertEquals(1200, $line->batches()->sole()->quantity);
    }

    /**
     * EN: Given one item bought on two lines of the same invoice, packed the same way at the same cost:
     *     100 packets all of power 1.00, and 100 packets all of power 1.50. 4 cartons of the 1.50 line are split off, then merged back.
     *     Then the part goes back into the 1.50 line, which is whole again (100 packets, all of 1.50),
     *     and the 1.00 line is untouched (100 packets).
     * AR: مادة وحدة انشترت ببندين بنفس الفاتورة، بنفس التعبئة ونفس الكلفة:
     *     100 باكيت كلهن درجة 1.00، و100 باكيت كلهن درجة 1.50. 4 كراتين من بند 1.50 تنفصل، وبعدين ترجع.
     *     فالجزء يرجع لبند 1.50، ويرجع كامل (100 باكيت، كلهن 1.50)،
     *     وبند 1.00 ما يتغيّر (100 باكيت).
     */
    public function test_a_part_goes_back_into_the_line_of_its_power(): void
    {
        $item = Item::factory()->create();
        $higherPower = LensPower::factory()->create(['value' => 1.5]);
        $invoice = $this->purchaseInvoice();
        $lowerLine = $this->addLineOf($invoice, $item, LensPower::factory()->create(['value' => 1]), boxesCount: 100);
        $higherLine = $this->addLineOf($invoice, $item, $higherPower, boxesCount: 100);
        $part = $this->split($invoice, [$higherLine->id => 4]);

        $this->merge($part);

        $this->assertEquals([$lowerLine->id, $higherLine->id], $invoice->items()->orderBy('id')->pluck('id')->all());
        $this->assertEquals(100, $lowerLine->fresh()->boxes_count);
        $this->assertEquals(100, $higherLine->fresh()->boxes_count);
        $this->assertEquals([$higherPower->id => 100], $higherLine->groups()->pluck('boxes_count', 'lens_power_id')->all());
    }

    /**
     * EN: Given a line of 30 packets of 10 spread over two powers (10 packets of 1.00 and 20 of 1.50), whose 3 cartons all go,
     *     so it leaves the original, and another line that stays. The part is merged back.
     *     Then the line comes back to the original as a new line with both powers and their packets,
     *     and a stock batch for each (100 pieces of 1.00 and 200 of 1.50).
     * AR: بند بيه 30 باكيت، كل باكيت 10 قطع، موزع على درجتين (10 باكيتات 1.00 و20 باكيت 1.50)، وكل كراتينه الثلاثة تروح،
     *     فيطلع من الأصلية، وبند ثاني يبقى. الجزء يرجع للأصلية.
     *     فالبند يرجع للأصلية كبند جديد بدرجتيه وباكيتاتهن،
     *     وإله دفعة لكل درجة (100 قطعة 1.00 و200 قطعة 1.50).
     */
    public function test_a_line_shipped_whole_comes_back_with_its_powers(): void
    {
        $lowPower = LensPower::factory()->create(['value' => 1]);
        $highPower = LensPower::factory()->create(['value' => 1.5]);
        $invoice = $this->purchaseInvoice();
        $shipped = $this->addLine($invoice, boxesCount: 30, boxQuantity: 10, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $shipped->groups()->createMany([
            ['lens_power_id' => $lowPower->id, 'boxes_count' => 10],
            ['lens_power_id' => $highPower->id, 'boxes_count' => 20],
        ]);
        $kept = $this->addLine($invoice, boxesCount: 10, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $part = $this->split($invoice, [$shipped->id => 3, $kept->id => 0]);

        $this->merge($part);

        $cameBack = $invoice->items()->where('item_id', $shipped->item_id)->sole();
        $this->assertEquals([$lowPower->id => 10, $highPower->id => 20], $cameBack->groups()->pluck('boxes_count', 'lens_power_id')->all());
        $this->assertEquals([$lowPower->id => 100, $highPower->id => 200], $cameBack->batches()->pluck('quantity', 'lens_power_id')->all());
    }

    /**
     * EN: Given a line of 100 packets of 24 pieces whose packet holds 10 red pieces and 14 blue.
     *     4 of its 10 cartons are split off, then merged back.
     *     Then the part goes back into the same line: 100 packets with the same colors in a packet,
     *     and its stock batch has 1000 red pieces and 1400 blue again.
     * AR: بند بيه 100 باكيت، كل باكيت 24 قطعة، وكل باكيت بيه 10 قطع أحمر و14 أزرق.
     *     4 كراتين من 10 تنفصل، وبعدين ترجع.
     *     فالجزء يرجع لنفس البند: 100 باكيت بنفس ألوان الباكيت،
     *     ودفعته ترجع بيها 1000 قطعة أحمر و1400 أزرق.
     */
    public function test_a_part_goes_back_into_the_line_of_its_colors(): void
    {
        $invoice = $this->purchaseInvoice();
        $line = $this->addLine($invoice, boxesCount: 100, boxQuantity: 24, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $line->colors()->createMany([
            ['color_id' => Color::factory()->create(['name' => 'Red'])->id, 'pieces' => 10],
            ['color_id' => Color::factory()->create(['name' => 'Blue'])->id, 'pieces' => 14],
        ]);
        $part = $this->split($invoice, [$line->id => 4]);

        $this->merge($part);

        $cameBack = $invoice->items()->sole();
        $this->assertEquals(100, $cameBack->boxes_count);
        $this->assertEquals(['Red' => 10, 'Blue' => 14], $cameBack->colors()->with('color')->get()->pluck('pieces', 'color.name')->all());
        $this->assertEquals(
            ['Red' => 1000, 'Blue' => 1400],
            $cameBack->batches()->sole()->colors()->with('color')->get()->pluck('pieces', 'color.name')->all(),
        );
    }

    /**
     * EN: Given a line of 30 packets of 24 pieces in 3 cartons whose packet holds 10 red pieces and 14 blue, spread over two powers:
     *     10 packets of 1.00 and 20 packets of 1.50. All its cartons go, and the part is merged back.
     *     Then the line comes back to the original with its colors and both powers, each with its packets.
     * AR: بند بيه 30 باكيت، كل باكيت 24 قطعة، بـ 3 كراتين، وكل باكيت بيه 10 قطع أحمر و14 أزرق، متوزع على درجتين:
     *     10 باكيتات 1.00 و20 باكيت 1.50. كل كراتينه تروح، والجزء يرجع.
     *     فالبند يرجع للأصلية بألوانه وبالدرجتين، وكل درجة بباكيتاتها.
     */
    public function test_a_line_shipped_whole_comes_back_with_its_colors_and_powers(): void
    {
        $invoice = $this->purchaseInvoice();
        $shipped = $this->addLine($invoice, boxesCount: 30, boxQuantity: 24, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $shipped->colors()->createMany([
            ['color_id' => Color::factory()->create(['name' => 'Red'])->id, 'pieces' => 10],
            ['color_id' => Color::factory()->create(['name' => 'Blue'])->id, 'pieces' => 14],
        ]);
        $shipped->groups()->create(['lens_power_id' => LensPower::factory()->create(['value' => 1])->id, 'boxes_count' => 10]);
        $shipped->groups()->create(['lens_power_id' => LensPower::factory()->create(['value' => 1.5])->id, 'boxes_count' => 20]);
        $kept = $this->addLine($invoice, boxesCount: 10, boxQuantity: 1, boxesPerCarton: 10, cartonCbm: 1, unitCost: 1);
        $part = $this->split($invoice, [$shipped->id => 3, $kept->id => 0]);

        $this->merge($part);

        $cameBack = $invoice->items()->where('item_id', $shipped->item_id)->sole();
        $this->assertEquals(['Red' => 10, 'Blue' => 14], $cameBack->colors()->with('color')->get()->pluck('pieces', 'color.name')->all());
        $this->assertEquals(
            ['1.00' => 10, '1.50' => 20],
            $cameBack->groups()->with('lensPower')->orderBy('id')->get()->mapWithKeys(fn ($group): array => [$group->lensPower->value => $group->boxes_count])->all(),
        );
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
            'pieces_per_packet' => $boxQuantity,
            'boxes_per_carton' => $boxesPerCarton,
            'carton_cbm' => $cartonCbm,
            'price' => $boxesCount * $boxQuantity * $unitCost,
        ]);
    }

    /**
     * EN: Adds a purchase line of the given item with all its packets at the given power: packets of 10 pieces, 10 packets per carton,
     *     1 CBM per carton, and 1 per piece.
     * AR: يضيف بند شراء للمادة المحددة وكل باكيتاته بالدرجة المحددة: باكيتات بيها 10 قطع، و10 باكيت بالكارتونة،
     *     والكارتونة 1 CBM، والقطعة بـ 1.
     */
    private function addLineOf(Invoice $invoice, Item $item, LensPower $power, float $boxesCount): InvoiceItem
    {
        $line = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => $boxesCount,
            'pieces_per_packet' => 10,
            'boxes_per_carton' => 10,
            'carton_cbm' => 1,
            'price' => $boxesCount * 10,
        ]);

        $line->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => $boxesCount]);

        return $line;
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
