<?php

namespace Tests\Feature\Admin\Reports;

use App\Enums\OrderPaymentType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    /**
     * An order on that day and payment type, with one line, and the order as it was saved.
     */
    private function order(string $date, OrderPaymentType $type, int $quantity = 2, int $price = 1000, ?Item $item = null): Order
    {
        $order = Order::factory()->create(['date' => $date, 'payment_type' => $type]);
        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => $quantity, 'price' => $price] + ($item ? ['item_id' => $item->id] : []));

        return $order->fresh();
    }

    /**
     * EN: The reports need a signed-in user.
     * AR: التقارير تحتاج يوزر مسجّل دخول.
     */
    public function test_the_reports_need_a_signed_in_user(): void
    {
        $this->getJson('/admin-api/reports/sales')->assertUnauthorized();
        $this->getJson('/admin-api/reports/profit-and-loss')->assertUnauthorized();
    }

    /**
     * EN: An unknown report is not found.
     * AR: التقرير اللي ما موجود يرجع "غير موجود".
     */
    public function test_an_unknown_report_is_not_found(): void
    {
        $this->actingAs($this->user())->getJson('/admin-api/reports/unknown')->assertNotFound();
    }

    /**
     * EN: Without a period the report opens on the last three months until today, like the panel.
     * AR: بدون فترة التقرير يفتح على آخر ثلاث أشهر لحد اليوم، مثل اللوحة.
     */
    public function test_without_a_period_the_report_opens_on_the_last_three_months(): void
    {
        $this->actingAs($this->user())->getJson('/admin-api/reports/sales')
            ->assertOk()
            ->assertJsonPath('from', CarbonImmutable::today()->subMonths(3)->toDateString())
            ->assertJsonPath('to', CarbonImmutable::today()->toDateString());
    }

    /**
     * EN: A period that ends before it starts is refused.
     * AR: الفترة اللي تخلص قبل ما تبدي تنرفض.
     */
    public function test_an_invalid_period_is_refused(): void
    {
        $this->actingAs($this->user());

        $this->getJson('/admin-api/reports/sales?from=2026-05-10&to=2026-05-01')->assertJsonValidationErrors('to');
    }

    /**
     * EN: The sales report has one row per day with its cash, postpaid and total sales, and the totals of the period; a day outside it and a deleted order are not counted.
     * AR: تقرير المبيعات بيه صف لكل يوم بمبيعاته الكاش والآجل ومجموعها، ومجاميع الفترة؛ اليوم اللي برّا الفترة والطلب المحذوف ما ينحسبون.
     */
    public function test_the_sales_report_rows_and_totals(): void
    {
        $cash = $this->order('2026-05-03', OrderPaymentType::Cash);
        $postpaid = $this->order('2026-05-03', OrderPaymentType::Postpaid, 3);
        $later = $this->order('2026-05-07', OrderPaymentType::Cash, 1);
        $this->order('2026-06-01', OrderPaymentType::Cash);
        $this->order('2026-05-04', OrderPaymentType::Cash)->delete();

        $response = $this->actingAs($this->user())
            ->getJson('/admin-api/reports/sales?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('key', 'sales')
            ->assertJsonPath('columns.1.name', 'cash')
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.day', '03/05')
            ->assertJsonCount(3, 'totals');

        $this->assertEquals($cash->total_amount, $response->json('rows.0.cash'));
        $this->assertEquals($postpaid->total_amount, $response->json('rows.0.postpaid'));
        $this->assertEquals($cash->total_amount + $postpaid->total_amount, $response->json('rows.0.total'));
        $this->assertEquals($cash->total_amount + $postpaid->total_amount + $later->total_amount, $response->json('totals.2.value'));
        $this->assertSame('success', $response->json('totals.0.color'));
        $this->assertNotNull($response->json('totals.0.formatted.currency'));
        $this->assertStringContainsString('/admin-api/reports/sales/excel?from=2026-05-01&to=2026-05-31', $response->json('excel_url'));
    }

    /**
     * EN: A period with no sales has no rows and no totals.
     * AR: الفترة اللي ما بيها مبيعات ما بيها صفوف ولا مجاميع.
     */
    public function test_a_period_without_data_is_empty(): void
    {
        $this->actingAs($this->user())
            ->getJson('/admin-api/reports/sales?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertJsonCount(0, 'rows')
            ->assertJsonCount(0, 'totals');
    }

    /**
     * EN: The purchases report has one row per supplier from the largest to the smallest, and each supplier opens the items bought from it in the period.
     * AR: تقرير المشتريات بيه صف لكل مورّد من الأكبر للأصغر، وكل مورّد يفتح المواد اللي انشترت منه بالفترة.
     */
    public function test_the_purchases_report_and_the_items_of_a_supplier(): void
    {
        $small = Invoice::factory()->purchase()->create(['date' => '2026-09-05']);
        InvoiceItem::factory()->create(['invoice_id' => $small->id, 'price' => 100, 'quantity' => 2]);
        $large = Invoice::factory()->purchase()->create(['date' => '2026-09-10']);
        $line = InvoiceItem::factory()->create(['invoice_id' => $large->id, 'price' => 900, 'quantity' => 5]);
        $outside = Invoice::factory()->purchase()->create(['date' => '2026-10-10', 'supplier_id' => $large->supplier_id]);
        InvoiceItem::factory()->create(['invoice_id' => $outside->id, 'price' => 5000]);

        $this->actingAs($this->user());

        $response = $this->getJson('/admin-api/reports/purchases?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.supplier_id', $large->supplier_id)
            ->assertJsonCount(1, 'totals');

        $this->assertEquals($large->fresh()->amount, $response->json('rows.0.amount'));
        $this->assertSame('CNY', $response->json('totals.0.currency'));

        $items = $this->getJson("/admin-api/reports/purchases/suppliers/{$large->supplier_id}/items?from=2026-09-01&to=2026-09-30")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', $line->fresh()->specificDescription->item->name);

        $this->assertEquals(900, $items->json('items.0.amount'));
        $this->assertEquals(900, $items->json('total'));
        $this->assertStringContainsString($large->supplier->name_ar, $items->json('heading'));
    }

    /**
     * EN: The item sales report ranks the items by the sold quantity, or by the sold value when asked.
     * AR: تقرير مبيعات المواد يرتّب المواد حسب الكمية المباعة، أو حسب القيمة المباعة إذا انطلبت.
     */
    public function test_the_item_sales_report_sorts_by_quantity_or_amount(): void
    {
        $many = Item::factory()->stocked()->create();
        $expensive = Item::factory()->stocked()->create();
        $this->order('2026-05-03', OrderPaymentType::Cash, 10, 100, $many);
        $this->order('2026-05-03', OrderPaymentType::Cash, 2, 5000, $expensive);

        $this->actingAs($this->user());

        $this->getJson('/admin-api/reports/item-sales?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('filters.sort', 'quantity')
            ->assertJsonPath('rows.0.item', $many->name)
            ->assertJsonPath('rows.0.rank', 1);

        $this->getJson('/admin-api/reports/item-sales?from=2026-05-01&to=2026-05-31&sort=amount')
            ->assertOk()
            ->assertJsonPath('filters.sort', 'amount')
            ->assertJsonPath('rows.0.item', $expensive->name);

        $this->getJson('/admin-api/reports/item-sales?sort=price')->assertJsonValidationErrors('sort');
    }

    /**
     * EN: The golden report takes the head of the chosen lists, keeps each item once with its lists, and sums the period in its summary.
     * AR: التقرير الذهبي ياخذ أول المواد من القوائم المختارة، يخلّي كل مادة مرة وحدة ويا قوائمها، ويلخّص الفترة.
     */
    public function test_the_golden_report_lists_and_summary(): void
    {
        $first = Item::factory()->stocked()->create();
        $second = Item::factory()->stocked()->create();
        $this->order('2026-05-03', OrderPaymentType::Cash, 10, 1000, $first);
        $this->order('2026-05-04', OrderPaymentType::Cash, 1, 500, $second);

        $this->actingAs($this->user());

        $response = $this->getJson('/admin-api/reports/golden?from=2026-05-01&to=2026-05-31&lists[]=amount&count=1')
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.item', $first->name)
            ->assertJsonPath('filters.lists', ['amount'])
            ->assertJsonPath('filters.count', 1);

        $this->assertEquals(Order::query()->sum('profit'), $response->json('summary.period_profit'));
        $this->assertNotEmpty($response->json('summary_text.period_profit'));

        $this->getJson('/admin-api/reports/golden?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('filters.count', 10);

        $this->getJson('/admin-api/reports/golden?lists[]=cost')->assertJsonValidationErrors('lists.0');
        $this->getJson('/admin-api/reports/golden?count=500')->assertJsonValidationErrors('count');
    }

    /**
     * EN: Every report downloads its fetched period as an Excel file.
     * AR: كل تقرير ينزّل فترته كملف إكسل.
     */
    public function test_every_report_downloads_an_excel_file(): void
    {
        $this->order('2026-05-03', OrderPaymentType::Cash);
        $this->actingAs($this->user());

        foreach (['sales', 'purchases', 'item-sales', 'golden'] as $report) {
            $response = $this->get("/admin-api/reports/{$report}/excel?from=2026-05-01&to=2026-05-31")->assertOk();
            $this->assertStringContainsString("{$report}_2026-05-01_2026-05-31.xlsx", (string) $response->headers->get('Content-Disposition'));
        }
    }
}
