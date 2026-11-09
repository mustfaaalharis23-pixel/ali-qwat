<?php

namespace Tests\Feature;

use App\Invoices\FetchDollarRate;
use App\Invoices\FetchMerchantsBankDollarRate;
use App\Models\Batch;
use App\Models\Color;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreatePurchaseInvoiceGroupsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A line of 10 packets of 24 pieces is spread on the new invoice as 3 packets of 0.50, 3 of 1.00, and 4 of 1.50.
     *     Then the line is saved with its spread, and it has a stock batch for each power: 72 pieces of 0.50, 72 of 1.00, and 96 of 1.50.
     * AR: بند بيه 10 باكيتات، كل باكيت 24 قطعة، يتوزع بالفاتورة الجديدة 3 باكيتات 0.50، و3 باكيتات 1.00، و4 باكيتات 1.50.
     *     فالبند ينحفظ بتوزيعه، وإله دفعة لكل درجة: 72 قطعة 0.50، و72 قطعة 1.00، و96 قطعة 1.50.
     */
    public function test_a_line_spread_over_powers_is_saved_on_a_new_invoice(): void
    {
        [$lowPower, $middlePower, $highPower] = [
            LensPower::factory()->create(['value' => 0.5]),
            LensPower::factory()->create(['value' => 1]),
            LensPower::factory()->create(['value' => 1.5]),
        ];

        $this->createWithSpread([
            ['lens_power_id' => $lowPower->id, 'boxes_count' => 3],
            ['lens_power_id' => $middlePower->id, 'boxes_count' => 3],
            ['lens_power_id' => $highPower->id, 'boxes_count' => 4],
        ])->assertCreated();

        $line = InvoiceItem::query()->sole();
        $this->assertEquals(
            [$lowPower->id => 3, $middlePower->id => 3, $highPower->id => 4],
            $line->groups()->pluck('boxes_count', 'lens_power_id')->all(),
        );
        $this->assertEquals(
            [$lowPower->id => 72, $middlePower->id => 72, $highPower->id => 96],
            Batch::query()->pluck('quantity', 'lens_power_id')->all(),
        );
    }

    /**
     * EN: The spreads of a line of 10 packets that do not add up to its packets:
     *     8 packets (2 packets would have no power), or 11 packets (more than the line has).
     * AR: توزيعات بند بيه 10 باكيتات ما يطلع مجموعها باكيتاته:
     *     8 باكيتات (باكيتين يبقن بلا درجة)، أو 11 باكيت (أكثر من اللي بالبند).
     *
     * @return array<string, array{list<float>}>
     */
    public static function spreadsThatDoNotAddUp(): array
    {
        return [
            'fewer packets than the line has' => [[3, 3, 2]],
            'more packets than the line has' => [[3, 3, 5]],
        ];
    }

    /**
     * EN: Such a spread is refused on the line's powers, and no invoice is saved.
     * AR: هيج توزيع ينرفض على درجات البند، وما تنحفظ فاتورة.
     *
     * @param  list<float>  $packets
     */
    #[DataProvider('spreadsThatDoNotAddUp')]
    public function test_a_spread_that_does_not_add_up_to_the_packets_is_refused(array $packets): void
    {
        $spread = collect($packets)->map(fn (float $count, int $index): array => [
            'lens_power_id' => LensPower::factory()->create(['value' => ($index + 1) / 2])->id,
            'boxes_count' => $count,
        ])->all();

        $this->createWithSpread($spread)->assertJsonValidationErrors('items.0.groups');

        $this->assertSame(0, Invoice::query()->count());
    }

    /**
     * EN: A line of 10 packets of 24 pieces is saved on a new invoice with its packet's colors chosen once for the whole line,
     *     10 red pieces and 14 blue, and its packets spread over two powers: 4 packets of 1.00 and 6 of 1.50.
     *     Then the line is saved with its colors once, its groups hold only their powers and packets,
     *     and the batch of each power has the line's colors in each of its packets: 1.00 has 40 red and 56 blue, 1.50 has 60 red and 84 blue.
     * AR: بند بيه 10 باكيتات، كل باكيت 24 قطعة، ينحفظ بفاتورة جديدة وألوان باكيته تنختار مرة وحدة للبند كله،
     *     10 قطع أحمر و14 أزرق، وباكيتاته تتوزع على درجتين: 4 باكيتات 1.00 و6 باكيتات 1.50.
     *     فالبند ينحفظ بألوانه مرة وحدة، ومجموعاته بيها بس درجاتها وباكيتاتها،
     *     ودفعة كل درجة بكل باكيت منها ألوان البند: 1.00 بيها 40 أحمر و56 أزرق، و1.50 بيها 60 أحمر و84 أزرق.
     */
    public function test_a_line_colors_are_saved_once_on_a_new_invoice(): void
    {
        $red = Color::factory()->create(['name' => 'Red']);
        $blue = Color::factory()->create(['name' => 'Blue']);
        $lowPower = LensPower::factory()->create(['value' => 1]);
        $highPower = LensPower::factory()->create(['value' => 1.5]);

        $this->createWithSpread(
            [
                ['lens_power_id' => $lowPower->id, 'boxes_count' => 4],
                ['lens_power_id' => $highPower->id, 'boxes_count' => 6],
            ],
            colors: [
                ['color_id' => $red->id, 'pieces' => 10],
                ['color_id' => $blue->id, 'pieces' => 14],
            ],
        )->assertCreated();

        $line = InvoiceItem::query()->sole();
        $this->assertEquals([$red->id => 10, $blue->id => 14], $line->colors()->pluck('pieces', 'color_id')->all());
        $this->assertEquals([$lowPower->id => 4, $highPower->id => 6], $line->groups()->pluck('boxes_count', 'lens_power_id')->all());
        $this->assertEquals(
            [$red->id => 40, $blue->id => 56],
            Batch::query()->where('lens_power_id', $lowPower->id)->sole()->colors()->pluck('pieces', 'color_id')->all(),
        );
        $this->assertEquals(
            [$red->id => 60, $blue->id => 84],
            Batch::query()->where('lens_power_id', $highPower->id)->sole()->colors()->pluck('pieces', 'color_id')->all(),
        );
    }

    /**
     * EN: The colors of a packet of 24 pieces that do not fill it: 10 + 10 pieces (4 pieces would have no color),
     *     or 10 + 20 pieces (more than the packet holds).
     * AR: ألوان باكيت بيه 24 قطعة ما تعبيه بالضبط: 10 + 10 قطع (4 قطع تبقى بلا لون)،
     *     أو 10 + 20 قطعة (أكثر من اللي يشيله الباكيت).
     *
     * @return array<string, array{list<float>}>
     */
    public static function colorsThatDoNotFillThePacket(): array
    {
        return [
            'fewer pieces than the packet holds' => [[10, 10]],
            'more pieces than the packet holds' => [[10, 20]],
        ];
    }

    /**
     * EN: Such colors are refused on the line's colors, and no invoice is saved.
     * AR: هيج ألوان تنرفض على ألوان البند، وما تنحفظ فاتورة.
     *
     * @param  list<float>  $pieces
     */
    #[DataProvider('colorsThatDoNotFillThePacket')]
    public function test_colors_that_do_not_fill_the_packet_are_refused(array $pieces): void
    {
        $colors = collect($pieces)->map(fn (float $count): array => [
            'color_id' => Color::factory()->create()->id,
            'pieces' => $count,
        ])->all();

        $this->createWithSpread([], colors: $colors)->assertJsonValidationErrors('items.0.colors');

        $this->assertSame(0, Invoice::query()->count());
    }

    /**
     * EN: Sends a new invoice to the admin API with one line of an item whose packet holds 24 pieces: 10 packets, 5 packets per carton,
     *     at 2 a piece, spread over powers as given, with the given colors in each packet.
     * AR: يبعث فاتورة جديدة للـ API بالبند الواحد لمادة باكيتها بيه 24 قطعة: 10 باكيتات، و5 باكيتات بالكارتونة،
     *     والقطعة بـ 2، موزع على الدرجات مثل ما انطى، وبكل باكيت الألوان المنطاة.
     *
     * @param  list<array{lens_power_id?: int, boxes_count: float}>  $spread
     * @param  list<array{color_id: int, pieces: float}>  $colors
     */
    private function createWithSpread(array $spread, array $colors = []): TestResponse
    {
        Storage::fake('public');
        // The new invoice asks the banks for the exchange rate when it opens; the test never goes out to them.
        $this->mock(FetchDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn(6.7));
        $this->mock(FetchMerchantsBankDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn(null));
        $this->actingAs(User::factory()->create(['is_active' => true, 'is_super' => true]));
        $this->getJson('/admin-api/purchase-invoices/create')->assertOk();

        return $this->postJson('/admin-api/purchase-invoices', [
            'name' => 'Reading glasses order',
            'supplier_id' => Supplier::factory()->create()->id,
            'invoice_category_id' => InvoiceCategory::factory()->create()->id,
            'date' => '2026-10-19',
            'exchange_rate' => 6.7,
            'dinar_rate' => 170,
            'cbm_price' => 120,
            'items' => [[
                'specific_description_id' => Item::factory()->create(['pieces_per_packet' => 24])->specificDescription->id,
                'entry_unit' => 'packets',
                'boxes_count' => 10,
                'pieces_per_packet' => 24,
                'boxes_per_carton' => 5,
                'carton_cbm' => 0.1,
                'unit_cost' => 2,
                'groups' => $spread,
                'colors' => $colors,
            ]],
        ]);
    }
}
