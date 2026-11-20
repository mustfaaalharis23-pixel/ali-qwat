<?php

namespace Tests\Unit;

use App\Invoices\ReadPurchaseExcel;
use App\Models\Color;
use App\Models\Item;
use App\Models\LensPower;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ReadPurchaseExcelTest extends TestCase
{
    use RefreshDatabase;

    private const string SUPPLIER_FILE = 'tests/Fixtures/purchase-excel/synthetic-reading-glasses.xlsx';

    /**
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * EN: The supplier's file is read item by item: 6 items over 10 rows, each with its rows, its packets and cartons,
     *     24 pieces a packet and 25 packets a carton, and in all 300 packets, 12 cartons, 7,200 pieces, and 11,280 yuan.
     * AR: ملف المورد ينقرا مادة مادة: 6 مواد على 10 أسطر، كل وحدة بأسطرها وباكيتاتها وكراتينها،
     *     24 قطعة بالباكيت و25 باكيت بالكارتون، والمجموع 300 باكيت، 12 كارتون، 7,200 قطعة، و11,280 يوان.
     */
    public function test_the_supplier_file_is_read_item_by_item(): void
    {
        $result = $this->readTheSupplierFile();

        $this->assertSame(['DEMO-01', '0007', 'DEMO-03', 'DEMO-04', 'DEMO-05', 'DEMO-06'], array_column($result['lines'], 'code'));
        $this->assertSame([[4, 5], [6], [7, 8], [9], [10, 11], [12, 13]], array_column($result['lines'], 'rows'));
        $this->assertEquals([50, 75, 50, 25, 50, 50], array_column($result['lines'], 'packets'));
        $this->assertEquals([2, 3, 2, 1, 2, 2], array_column($result['lines'], 'cartons'));
        $this->assertEquals(array_fill(0, 6, 24), array_column($result['lines'], 'pieces_per_packet'));
        $this->assertEquals(array_fill(0, 6, 25), array_column($result['lines'], 'boxes_per_carton'));
        $this->assertEquals(['items' => 6, 'rows' => 10, 'packets' => 300, 'cartons' => 12, 'pieces' => 7200, 'amount' => 11280], $result['summary']['read']);
    }

    /**
     * EN: The powers and colors are read as the supplier writes them: "+50-25box" is 25 packets of power 0.50, with or without
     *     a slash between two, "BOX" in capitals, and a color's pieces even on the next line ("blue&white \n4").
     * AR: الدرجات والألوان تنقرا مثل ما يكتبها المورد: "+50-25box" يعني 25 باكيت درجة 0.50، بسلاش بين الثنتين أو بدونه،
     *     و"BOX" بالكابيتال، وقطع اللون حتى لو بالسطر الجاي ("blue&white \n4").
     */
    public function test_the_powers_and_colors_are_read_as_written(): void
    {
        $lines = collect($this->readTheSupplierFile()['lines'])->keyBy('code');

        $this->assertEquals(
            [[0.5, 5], [1, 10], [1.5, 12], [2, 13], [2.5, 5], [3, 3], [4, 2]],
            array_map(fn (array $group): array => [$group['power'], $group['packets']], $lines['DEMO-06']['groups']),
        );
        $this->assertEquals(
            [[0.5, 25], [1, 30]],
            array_map(fn (array $group): array => [$group['power'], $group['packets']], $lines['DEMO-01']['groups']),
        );
        $this->assertEquals(
            [['black&white', 12], ['brown&white', 8], ['blue&white', 4]],
            array_map(fn (array $color): array => [$color['name'], $color['pieces']], $lines['DEMO-04']['colors']),
        );
        $this->assertEquals(
            [['grey', 10], ['orange', 8], ['red', 6]],
            array_map(fn (array $color): array => [$color['name'], $color['pieces']], $lines['0007']['colors']),
        );
    }

    /**
     * EN: With its items, colors, and powers known, only the file's own mistakes are found in it: the power counts in DEMO-01 and 0007
     *     exceed their packet counts, and its total says 12,000 yuan while its rows make 11,280. Each says it in words.
     * AR: لمن موادها وألوانها ودرجاتها معروفة، بس أغلاط الملف نفسه تطلع: درجات DEMO-01 و0007 تزيد على عدد الباكيتات،
     *     ومجموعه يگول 12,000 يوان وأسطره تطلع 11,280. وكل غلط مكتوب بالكلام.
     */
    public function test_only_the_files_own_mistakes_are_found(): void
    {
        $result = $this->readTheSupplierFile();

        $this->assertSame(
            ['DEMO-01' => ['groups_must_add_up'], '0007' => ['groups_must_add_up'], 'DEMO-03' => [], 'DEMO-04' => [], 'DEMO-05' => [], 'DEMO-06' => []],
            $this->typesByCode($result['lines'], 'error'),
        );
        $this->assertEquals(['spread' => 55, 'packets' => 50], $this->issue($result['lines'][0]['issues'], 'groups_must_add_up')['values']);
        $this->assertSame(['total_amount_differs'], $this->types($result['issues']));
        $this->assertEquals(['written' => 12000, 'read' => 11280], $result['issues'][0]['values']);
        $this->assertEquals(['packets' => 300, 'cartons' => 12, 'amount' => 12000], $result['summary']['written']);

        foreach ([...$result['issues'], ...collect($result['lines'])->pluck('issues')->flatten(1)] as $issue) {
            $this->assertNotSame('', $issue['message']);
            $this->assertStringNotContainsString('invoices.', $issue['message']);
        }
    }

    /**
     * EN: An item written in two rows at two prices (DEMO-01: 25 packets at 1.1 and 25 at 1.3) is one line at the average price
     *     of its pieces (1.2), so its amount stays exact (1,440); both prices are kept and a warning says so.
     * AR: المادة المكتوبة بسطرين بسعرين (3106: 25 باكيت بـ 1.1 و25 بـ 1.3) تصير بند واحد بمتوسط سعر قطعها (1.2)،
     *     فمبلغها يبقى مضبوط (1,440)؛ والسعرين ينحفظون ويطلع تنبيه بيهم.
     */
    public function test_two_price_rows_make_one_line_at_the_average_price(): void
    {
        $result = $this->readTheSupplierFile();
        $line = $result['lines'][0];

        $this->assertEqualsWithDelta(1.2, $line['unit_cost'], 0.00001);
        $this->assertEqualsWithDelta(1440, $line['amount'], 0.001);
        $this->assertEquals([['row' => 4, 'packets' => 25, 'price' => 1.1], ['row' => 5, 'packets' => 25, 'price' => 1.3]], $line['prices']);
        $this->assertEqualsWithDelta(1.6, $result['lines'][2]['unit_cost'], 0.00001);
        $this->assertSame(
            ['DEMO-01' => ['two_prices_averaged'], '0007' => [], 'DEMO-03' => ['two_prices_averaged'], 'DEMO-04' => [], 'DEMO-05' => ['two_prices_averaged'], 'DEMO-06' => ['two_prices_averaged']],
            $this->typesByCode($result['lines'], 'warning'),
        );
    }

    /**
     * EN: An item is found by its name being the code, in any letter case; a code no item has, a code two items have,
     *     or an item whose packet differs from the file's is reported on its line, and the line is still read.
     * AR: المادة تنلگى لمن اسمها هو الكود، بأي حروف كبيرة أو صغيرة؛ والكود اللي ماكو مادة بيه، أو بيه مادتين،
     *     أو المادة اللي تعبئة باكيتها غير الملف، ينذكر على بنده، والبند ينقرا عادي.
     */
    public function test_items_are_found_by_their_code(): void
    {
        $found = Item::factory()->create(['name' => 'a-02', 'pieces_per_packet' => 24]);
        Item::factory()->count(2)->create(['name' => 'X-1']);
        Item::factory()->create(['name' => 'B-7', 'pieces_per_packet' => 20]);
        Color::factory()->create(['name' => 'black']);
        LensPower::factory()->create(['value' => 0.5]);

        $lines = $this->read($this->supplierFile([
            $this->itemRow('A-02', '+50-25box', 'black 24', packets: 25, price: 1.6),
            $this->itemRow('Z-9', '+50-25box', 'black 24', packets: 25, price: 1.6),
            $this->itemRow('X-1', '+50-25box', 'black 24', packets: 25, price: 1.6),
            $this->itemRow('B-7', '+50-25box', 'black 24', packets: 25, price: 1.6),
        ]))['lines'];

        $this->assertEquals(['id' => $found->id, 'name' => 'a-02', 'specific_description_id' => $found->specificDescription->id], $lines[0]['item']);
        $this->assertSame([], $lines[0]['issues']);
        $this->assertNull($lines[1]['item']);
        $this->assertSame(['item_not_found'], $this->types($lines[1]['issues']));
        $this->assertEquals(['code' => 'Z-9'], $lines[1]['issues'][0]['values']);
        $this->assertNull($lines[2]['item']);
        $this->assertSame(['item_ambiguous'], $this->types($lines[2]['issues']));
        $this->assertSame(['packet_differs_from_item'], $this->types($lines[3]['issues']));
        $this->assertEquals(['file' => 24, 'item' => 20], $lines[3]['issues'][0]['values']);
        $this->assertEquals(25, $lines[1]['packets']);
    }

    /**
     * EN: A color is found by its name in any letter case, and a power by its value (+125 is 1.25); a color or a power
     *     the system does not have is kept with its name or value and reported, never dropped.
     * AR: اللون ينلگى باسمه بأي حروف، والدرجة بقيمتها (+125 يعني 1.25)؛ واللون أو الدرجة اللي ما موجودة بالنظام
     *     تبقى باسمها أو قيمتها وتنذكر، وما تنشال أبد.
     */
    public function test_colors_and_powers_are_found_or_reported(): void
    {
        Item::factory()->create(['name' => 'A-02']);
        $black = Color::factory()->create(['name' => 'Black']);
        $power = LensPower::factory()->create(['value' => 1.25]);

        $line = $this->read($this->supplierFile([
            $this->itemRow('A-02', '+125-20box/+600-5box', "black 12\npink 12", packets: 25, price: 1.6),
        ]))['lines'][0];

        $this->assertSame([['name' => 'black', 'pieces' => 12, 'color_id' => $black->id], ['name' => 'pink', 'pieces' => 12, 'color_id' => null]], $line['colors']);
        $this->assertEquals([['power' => 1.25, 'packets' => 20, 'lens_power_id' => $power->id], ['power' => 6, 'packets' => 5, 'lens_power_id' => null]], $line['groups']);
        $this->assertEqualsCanonicalizing(['color_not_found', 'lens_power_not_found'], $this->types($line['issues']));
        $this->assertEquals(['name' => 'pink'], $this->issue($line['issues'], 'color_not_found')['values']);
        $this->assertEquals(['power' => 6], $this->issue($line['issues'], 'lens_power_not_found')['values']);
    }

    /**
     * EN: Text that cannot be read as powers or colors is reported with the part that was not understood, never skipped:
     *     "+100-x" among the powers, and "brown" without its pieces among the colors.
     * AR: الكلام اللي ما ينقرا درجات أو ألوان ينذكر ويا الجزء اللي ما انفهم، وما يتعدى أبد:
     *     "+100-x" بين الدرجات، و"brown" بلا عدد قطع بين الألوان.
     */
    public function test_text_that_cannot_be_read_is_reported(): void
    {
        Item::factory()->create(['name' => 'A-02']);
        Color::factory()->create(['name' => 'black']);
        LensPower::factory()->create(['value' => 0.5]);

        $line = $this->read($this->supplierFile([
            $this->itemRow('A-02', '+50-25box/+100-x', 'black 24, brown', packets: 25, price: 1.6),
        ]))['lines'][0];

        $this->assertEqualsCanonicalizing(['powers_unreadable', 'colors_unreadable'], $this->types($line['issues']));
        $this->assertEquals(['text' => '+100-x'], $this->issue($line['issues'], 'powers_unreadable')['values']);
        $this->assertEquals(['text' => 'brown'], $this->issue($line['issues'], 'colors_unreadable')['values']);
        $this->assertEquals([[0.5, 25]], array_map(fn (array $group): array => [$group['power'], $group['packets']], $line['groups']));
    }

    /**
     * EN: The numbers the file writes are checked against what they are made of: cartons against packets ÷ packets a carton,
     *     a row's pieces against packets × the packet, its amount against pieces × price (warnings),
     *     and colors that do not fill the packet (an error).
     * AR: الأرقام المكتوبة بالملف تنفحص ويا اللي تتكون منه: الكراتين ويا الباكيتات ÷ باكيتات الكارتون،
     *     وقطع السطر ويا الباكيتات × التعبئة، ومبلغه ويا القطع × السعر (تنبيهات)،
     *     والألوان اللي ما تملي الباكيت (غلط).
     */
    public function test_the_written_numbers_are_checked(): void
    {
        Item::factory()->create(['name' => 'A-02']);
        Color::factory()->create(['name' => 'black']);
        LensPower::factory()->create(['value' => 0.5]);

        $line = $this->read($this->supplierFile([
            $this->itemRow('A-02', '+50-50box', 'black 22', packets: 50, price: 1.6, written: ['F' => 3, 'J' => 1000, 'L' => 999]),
        ]))['lines'][0];

        $this->assertEqualsCanonicalizing(['cartons_differ', 'pieces_differ', 'amount_differs', 'colors_must_fill_the_packet'], $this->types($line['issues']));
        $this->assertEquals(['written' => 3, 'read' => 2], $this->issue($line['issues'], 'cartons_differ')['values']);
        $this->assertEquals(['written' => 1000, 'read' => 1200], $this->issue($line['issues'], 'pieces_differ')['values']);
        $this->assertEquals(['written' => 999, 'read' => 1920], $this->issue($line['issues'], 'amount_differs')['values']);
        $this->assertEquals(['pieces' => 22, 'packet' => 24], $this->issue($line['issues'], 'colors_must_fill_the_packet')['values']);
        $this->assertSame('warning', $this->issue($line['issues'], 'amount_differs')['level']);
        $this->assertSame('error', $this->issue($line['issues'], 'colors_must_fill_the_packet')['level']);
        $this->assertEquals(2, $line['cartons']);
        $this->assertEqualsWithDelta(1920, $line['amount'], 0.001);
    }

    /**
     * EN: An item without its packets, price, pieces a packet, or pieces a carton is still read, with an error naming
     *     each number that is missing.
     * AR: المادة اللي ناقصها الباكيتات أو السعر أو قطع الباكيت أو قطع الكارتون تنقرا برضه، ويا غلط يسمي
     *     كل رقم ناقص.
     */
    public function test_missing_numbers_are_named(): void
    {
        Item::factory()->create(['name' => 'A-02']);
        Color::factory()->create(['name' => 'black']);
        LensPower::factory()->create(['value' => 0.5]);

        $lines = $this->read($this->supplierFile([
            $this->itemRow('A-02', '+50-25box', 'black 24', packets: 25, price: 1.6, written: ['I' => null, 'K' => null]),
            $this->itemRow('A-02', '+50-25box', 'black 24', packets: 25, price: 1.6, written: ['G' => null, 'H' => null]),
        ]))['lines'];

        $this->assertCount(2, $lines);
        $this->assertEqualsCanonicalizing(['packets', 'price'], $this->issue($lines[0]['issues'], 'missing_numbers')['values']['fields']);
        $this->assertEqualsCanonicalizing(['pieces_per_packet', 'pieces_per_carton'], $this->issue($lines[1]['issues'], 'missing_numbers')['values']['fields']);
        $this->assertSame('error', $this->issue($lines[0]['issues'], 'missing_numbers')['level']);
    }

    /**
     * EN: Every row is accounted for: a priced row before any item is reported, a row of text only is a note, and the totals
     *     row gives the totals the file writes, each checked against the lines (cartons 3 for 2, packets 60 for 50).
     * AR: كل سطر محسوب: السطر اللي بي سعر قبل أي مادة ينذكر، والسطر اللي بي كلام بس ملاحظة، وسطر المجموع
     *     يعطي المجاميع اللي يكتبها الملف، وكل وحدة تنفحص ويا البنود (كراتين 3 بدل 2، وباكيتات 60 بدل 50).
     */
    public function test_every_row_is_accounted_for(): void
    {
        Item::factory()->create(['name' => 'A-02']);
        Color::factory()->create(['name' => 'black']);
        LensPower::factory()->create(['value' => 0.5]);

        $result = $this->read($this->supplierFile([
            ['I' => 10, 'J' => 240, 'K' => 1.5, 'L' => 360],
            $this->itemRow('A-02', '+50-50box', 'black 24', packets: 50, price: 1.6),
            ['C' => '4面唛头：'],
            ['F' => 3, 'I' => 60, 'L' => 1920],
        ]));

        $this->assertCount(1, $result['lines']);
        $this->assertEqualsCanonicalizing(['row_without_item', 'total_cartons_differs', 'total_packets_differs'], $this->types($result['issues']));
        $this->assertSame(4, $this->issue($result['issues'], 'row_without_item')['row']);
        $this->assertSame('error', $this->issue($result['issues'], 'row_without_item')['level']);
        $this->assertEquals(['written' => 3, 'read' => 2], $this->issue($result['issues'], 'total_cartons_differs')['values']);
        $this->assertEquals(['written' => 60, 'read' => 50], $this->issue($result['issues'], 'total_packets_differs')['values']);
        $this->assertEquals(['packets' => 60, 'cartons' => 3, 'amount' => 1920], $result['summary']['written']);
    }

    /**
     * EN: A file that is not in the supplier's layout, or that has no item in it, is refused as a whole with a message.
     * AR: الملف اللي مو بترتيب المورد، أو اللي ماكو بي ولا مادة، ينرفض كله ويا رسالة.
     */
    public function test_a_file_in_another_layout_or_without_items_is_refused(): void
    {
        foreach ([
            $this->supplierFile([$this->itemRow('A-02', '+50-25box', 'black 24', packets: 25, price: 1.6)], headers: ['B' => 'Item', 'K' => 'Qty']),
            $this->supplierFile([['C' => '4面唛头：']]),
        ] as $path) {
            try {
                $this->read($path);
                $this->fail('The file was read.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
            }
        }
    }

    /**
     * Reads the supplier's file with its six items, its colors, and the powers from 0.50 to 4.50 known.
     *
     * @return array<string, mixed>
     */
    private function readTheSupplierFile(): array
    {
        foreach (['DEMO-01', '0007', 'DEMO-03', 'DEMO-04', 'DEMO-05', 'DEMO-06'] as $code) {
            Item::factory()->create(['name' => $code, 'pieces_per_packet' => 24]);
        }

        foreach (['black', 'brown', 'grey', 'orange', 'red', 'black&white', 'brown&white', 'blue&white'] as $name) {
            Color::factory()->create(['name' => $name]);
        }

        foreach (range(50, 450, 25) as $hundreds) {
            LensPower::factory()->create(['value' => $hundreds / 100]);
        }

        return $this->read(base_path(self::SUPPLIER_FILE));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $path): array
    {
        return (new ReadPurchaseExcel)->handle($path);
    }

    /**
     * Writes a file in the supplier's layout: its three header rows, then the given rows, each keyed by its column letter.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>|null  $headers
     */
    private function supplierFile(array $rows, ?array $headers = null): string
    {
        $path = sys_get_temp_dir().'/'.Str::uuid().'.xlsx';
        $this->files[] = $path;
        $headers ??= ['A' => 'MARK', 'B' => 'MARK', 'C' => 'name', 'D' => 'name', 'F' => 'CTNS', 'G' => 'CTNS/PCS', 'H' => 'box', 'I' => 'ALL BOX', 'J' => 'ALL PCS', 'K' => 'PRICE', 'L' => 'AMOUNT', 'M' => 'CBM'];

        $writer = new Writer;
        $writer->openToFile($path);

        foreach ([['B' => '客户代码', 'K' => '价格'], $headers, ['B' => 'رقم البضاعة', 'K' => 'سعره المفرد'], ...$rows] as $row) {
            $writer->addRow(Row::fromValues(array_map(fn (string $column): mixed => $row[$column] ?? null, range('A', 'M'))));
        }

        $writer->close();

        return $path;
    }

    /**
     * One item row with its numbers worked out the way the supplier's formulas do, unless written otherwise.
     *
     * @param  array<string, mixed>  $written
     * @return array<string, mixed>
     */
    private function itemRow(string $code, string $powers, string $colors, int $packets, float $price, int $packet = 24, int $perCarton = 600, array $written = []): array
    {
        return [
            'B' => $code,
            'C' => $powers,
            'E' => $colors,
            'F' => (int) ceil($packets / ($perCarton / $packet)),
            'G' => $perCarton,
            'H' => $packet,
            'I' => $packets,
            'J' => $packets * $packet,
            'K' => $price,
            'L' => $packets * $packet * $price,
            ...$written,
        ];
    }

    /**
     * @param  array<int, array{type: string}>  $issues
     * @return list<string>
     */
    private function types(array $issues): array
    {
        return array_column($issues, 'type');
    }

    /**
     * The types of each line's issues of one level, keyed by the line's code.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, list<string>>
     */
    private function typesByCode(array $lines, string $level): array
    {
        return collect($lines)
            ->mapWithKeys(fn (array $line): array => [$line['code'] => $this->types(array_filter($line['issues'], fn (array $issue): bool => $issue['level'] === $level))])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    private function issue(array $issues, string $type): array
    {
        return collect($issues)->firstWhere('type', $type) ?? $this->fail("There is no {$type} issue.");
    }
}
