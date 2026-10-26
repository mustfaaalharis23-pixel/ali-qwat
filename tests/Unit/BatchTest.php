<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\BatchColor;
use App\Models\Color;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\SpecificDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A purchase line creates one batch with its item, invoice, quantity, and unit price (line price / quantity).
     * AR: بند الشراء ينشئ دفعة وحدة بيها مادته وفاتورته وكميته وسعر الوحدة (سعر البند / الكمية).
     */
    public function test_purchase_line_creates_a_batch_with_the_unit_price(): void
    {
        $item = Item::factory()->create();
        $invoice = Invoice::factory()->purchase()->create();

        $line = $this->purchaseLine($invoice, $item, [
            'boxes_count' => 2,
            'pieces_per_packet' => 12,
            'price' => 2400,
        ]);

        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();

        $this->assertSame($item->id, $batch->item_id);
        $this->assertSame($invoice->id, $batch->invoice_id);
        $this->assertEquals(24, $batch->quantity);
        $this->assertEquals(100, $batch->purchase_price);
    }

    /**
     * EN: A general invoice line creates no batch.
     * AR: بند الفاتورة العامة ما ينشئ دفعة.
     */
    public function test_general_invoice_line_creates_no_batch(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $invoice->general_description_id])->id,
            'price' => 500,
        ]);

        $this->assertSame(0, Batch::query()->count());
    }

    /**
     * EN: Updating a line updates its batch, without creating a second batch.
     * AR: تعديل البند يعدل دفعته، بدون ما ينشئ دفعة ثانية.
     */
    public function test_updating_a_line_updates_its_batch(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create());

        $line->update(['boxes_count' => 2, 'price' => 3000]);

        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();

        $this->assertEquals(20, $batch->quantity);
        $this->assertEquals(150, $batch->purchase_price);
    }

    /**
     * EN: Deleting a line soft deletes its batch.
     * AR: حذف البند يحذف دفعته soft delete.
     */
    public function test_deleting_a_line_soft_deletes_its_batch(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create());
        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();

        $line->delete();

        $this->assertSoftDeleted($batch);
    }

    /**
     * EN: Given a line of 10 packets of 12 pieces for 1200 (10 a piece), spread over three powers: 3 packets of 0.50, 3 of 1.00, and 4 of 1.50.
     *     Then the line has a batch for each power: 36 pieces of 0.50, 36 of 1.00, and 48 of 1.50, all at 10 a piece,
     *     and no batch without a power.
     * AR: بند بيه 10 باكيتات، كل باكيت 12 قطعة، بـ 1200 (القطعة بـ 10)، وموزع على ثلث درجات: 3 باكيتات 0.50، و3 باكيتات 1.00، و4 باكيتات 1.50.
     *     فالبند تصير إله دفعة لكل درجة: 36 قطعة 0.50، و36 قطعة 1.00، و48 قطعة 1.50، وكلهن القطعة بـ 10،
     *     وماكو دفعة بلا درجة.
     */
    public function test_a_line_spread_over_powers_has_a_batch_for_each_power(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 10,
            'pieces_per_packet' => 12,
            'price' => 1200,
        ]);

        $line->groups()->createMany([
            ['lens_power_id' => LensPower::factory()->create(['value' => 0.5])->id, 'boxes_count' => 3],
            ['lens_power_id' => LensPower::factory()->create(['value' => 1])->id, 'boxes_count' => 3],
            ['lens_power_id' => LensPower::factory()->create(['value' => 1.5])->id, 'boxes_count' => 4],
        ]);

        $this->assertEquals(['0.50' => 36, '1.00' => 36, '1.50' => 48], $this->batchesOf($line));
        $this->assertEquals([10, 10, 10], Batch::query()->where('invoice_item_id', $line->id)->pluck('purchase_price')->all());
    }

    /**
     * EN: Given the same line spread as 3 packets of 0.50, 3 of 1.00, and 4 of 1.50.
     *     The 1.00 power is taken off the line, and the 0.50 power gets its 3 packets (6 packets).
     *     Then the batches follow: 72 pieces of 0.50 and 48 of 1.50, and the batch of 1.00 is gone.
     *     And when the line's packet holds 10 pieces instead of 12, the batches follow again: 60 of 0.50 and 40 of 1.50.
     * AR: نفس البند موزع 3 باكيتات 0.50، و3 باكيتات 1.00، و4 باكيتات 1.50.
     *     تنشال درجة 1.00 من البند، وتنضاف باكيتاتها الثلاثة على 0.50 (تصير 6 باكيتات).
     *     فالدفعات تتبعها: 72 قطعة 0.50، و48 قطعة 1.50، ودفعة 1.00 تروح.
     *     ولمن باكيت البند يصير بيه 10 قطع بدل 12، الدفعات تتبعه هم: 60 قطعة 0.50 و40 قطعة 1.50.
     */
    public function test_the_batches_follow_the_changes_of_the_spread(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 10,
            'pieces_per_packet' => 12,
            'price' => 1200,
        ]);

        [$lowPower, $middlePower] = $line->groups()->createMany([
            ['lens_power_id' => LensPower::factory()->create(['value' => 0.5])->id, 'boxes_count' => 3],
            ['lens_power_id' => LensPower::factory()->create(['value' => 1])->id, 'boxes_count' => 3],
            ['lens_power_id' => LensPower::factory()->create(['value' => 1.5])->id, 'boxes_count' => 4],
        ])->all();

        $middlePower->delete();
        $lowPower->update(['boxes_count' => 6]);

        $this->assertEquals(['0.50' => 72, '1.50' => 48], $this->batchesOf($line));

        $line->update(['pieces_per_packet' => 10]);

        $this->assertEquals(['0.50' => 60, '1.50' => 40], $this->batchesOf($line));
    }

    /**
     * EN: Deleting a line spread over two powers soft deletes both of its batches.
     * AR: حذف بند موزع على درجتين يحذف دفعتيه الثنتين حذف ناعم.
     */
    public function test_deleting_a_line_spread_over_powers_soft_deletes_all_its_batches(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 2,
        ]);

        $line->groups()->createMany([
            ['lens_power_id' => LensPower::factory()->create(['value' => 0.5])->id, 'boxes_count' => 1],
            ['lens_power_id' => LensPower::factory()->create(['value' => 1])->id, 'boxes_count' => 1],
        ]);

        $line->delete();

        $this->assertSame(0, Batch::query()->where('invoice_item_id', $line->id)->count());
        $this->assertSame(2, Batch::onlyTrashed()->where('invoice_item_id', $line->id)->count());
    }

    /**
     * EN: Given a line of 10 packets of 24 pieces whose packet holds 10 red pieces and 14 blue, chosen once for the whole line,
     *     and spread over two powers: 6 packets of 1.00 and 4 packets of 1.50.
     *     Then each power has its own batch with the line's colors in each of its packets:
     *     1.00 has 144 pieces with 60 red and 84 blue, and 1.50 has 96 pieces with 40 red and 56 blue.
     * AR: بند بيه 10 باكيتات، كل باكيت 24 قطعة، وكل باكيت بيه 10 قطع أحمر و14 أزرق، تنختار مرة وحدة للبند كله،
     *     ويتوزع على درجتين: 6 باكيتات 1.00 و4 باكيتات 1.50.
     *     فكل درجة إلها دفعة وبكل باكيت منها ألوان البند:
     *     1.00 بيها 144 قطعة منها 60 أحمر و84 أزرق، و1.50 بيها 96 قطعة منها 40 أحمر و56 أزرق.
     */
    public function test_the_line_colors_fill_the_packets_of_each_of_its_powers(): void
    {
        $red = Color::factory()->create(['name' => 'Red']);
        $blue = Color::factory()->create(['name' => 'Blue']);
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 10,
            'pieces_per_packet' => 24,
            'price' => 2000,
        ]);

        $line->colors()->createMany([
            ['color_id' => $red->id, 'pieces' => 10],
            ['color_id' => $blue->id, 'pieces' => 14],
        ]);
        $line->groups()->create(['lens_power_id' => LensPower::factory()->create(['value' => 1])->id, 'boxes_count' => 6]);
        $line->groups()->create(['lens_power_id' => LensPower::factory()->create(['value' => 1.5])->id, 'boxes_count' => 4]);

        $this->assertEquals([
            ['power' => '1.00', 'pieces' => 144, 'colors' => ['Red' => 60, 'Blue' => 84]],
            ['power' => '1.50', 'pieces' => 96, 'colors' => ['Red' => 40, 'Blue' => 56]],
        ], $this->batchesWithColorsOf($line));
    }

    /**
     * EN: Given a line of 5 packets of 24 pieces without powers, whose packet holds 12 red pieces and 12 black.
     *     Then it has one batch without a power, of 120 pieces with 60 red and 60 black.
     * AR: بند بيه 5 باكيتات، كل باكيت 24 قطعة، بلا درجات، وكل باكيت بيه 12 قطعة أحمر و12 أسود.
     *     فإله دفعة وحدة بلا درجة، بيها 120 قطعة منها 60 أحمر و60 أسود.
     */
    public function test_a_line_with_colors_and_no_powers_has_one_batch_with_its_colors(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 5,
            'pieces_per_packet' => 24,
            'price' => 1000,
        ]);

        $line->colors()->createMany([
            ['color_id' => Color::factory()->create(['name' => 'Red'])->id, 'pieces' => 12],
            ['color_id' => Color::factory()->create(['name' => 'Black'])->id, 'pieces' => 12],
        ]);

        $this->assertEquals([
            ['power' => null, 'pieces' => 120, 'colors' => ['Red' => 60, 'Black' => 60]],
        ], $this->batchesWithColorsOf($line));
    }

    /**
     * EN: Given a line of 6 packets whose packet holds 10 red pieces and 14 blue.
     *     The blue is taken off the line, the red becomes 24 pieces in a packet, and the line becomes 5 packets.
     *     Then its batch follows: 120 pieces, all 120 of them red, and no blue.
     * AR: بند بيه 6 باكيتات، كل باكيت بيه 10 قطع أحمر و14 أزرق.
     *     ينشال الأزرق من البند، ويصير الأحمر 24 قطعة بالباكيت، ويصير البند 5 باكيتات.
     *     فدفعته تتبعه: 120 قطعة، كلهن أحمر، وماكو أزرق.
     */
    public function test_the_colors_of_a_batch_follow_the_changes_of_its_line(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 6,
            'pieces_per_packet' => 24,
            'price' => 1200,
        ]);
        [$red, $blue] = $line->colors()->createMany([
            ['color_id' => Color::factory()->create(['name' => 'Red'])->id, 'pieces' => 10],
            ['color_id' => Color::factory()->create(['name' => 'Blue'])->id, 'pieces' => 14],
        ])->all();

        $blue->delete();
        $red->update(['pieces' => 24]);
        $line->update(['boxes_count' => 5]);

        $this->assertEquals([
            ['power' => null, 'pieces' => 120, 'colors' => ['Red' => 120]],
        ], $this->batchesWithColorsOf($line));
    }

    /**
     * EN: Each batch of the line that is not deleted, in the order of its group: its power, its pieces, and the pieces of each of its colors.
     * AR: كل دفعة غير محذوفة للبند، بترتيب مجموعتها: درجتها، وقطعها، وقطع كل لون بيها.
     *
     * @return list<array{power: ?string, pieces: float, colors: array<string, float>}>
     */
    private function batchesWithColorsOf(InvoiceItem $line): array
    {
        return Batch::query()
            ->where('invoice_item_id', $line->id)
            ->with(['lensPower', 'colors.color'])
            ->orderBy('invoice_item_group_id')
            ->get()
            ->map(fn (Batch $batch): array => [
                'power' => $batch->lensPower?->value,
                'pieces' => $batch->quantity,
                'colors' => $batch->colors->mapWithKeys(fn (BatchColor $color): array => [$color->color->name => $color->pieces])->all(),
            ])
            ->all();
    }

    /**
     * EN: The pieces of each batch of the line that is not deleted, keyed by its power, from the lowest power.
     * AR: قطع كل دفعة غير محذوفة للبند، مفتاحها درجتها، من الدرجة الأصغر.
     *
     * @return array<string, float>
     */
    private function batchesOf(InvoiceItem $line): array
    {
        return Batch::query()
            ->where('invoice_item_id', $line->id)
            ->with('lensPower')
            ->get()
            ->sortBy(fn (Batch $batch): float => (float) $batch->lensPower?->value)
            ->mapWithKeys(fn (Batch $batch): array => [(string) $batch->lensPower?->value => $batch->quantity])
            ->all();
    }

    /**
     * EN: Creates a purchase line for the item: one box of 10 for 1000, unless other values are given.
     * AR: ينشئ بند شراء للمادة: كارتون واحد بيه 10 بسعر 1000، إلا إذا انطت قيم ثانية.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function purchaseLine(Invoice $invoice, Item $item, array $attributes = []): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 10,
            'price' => 1000,
            ...$attributes,
        ]);
    }
}
