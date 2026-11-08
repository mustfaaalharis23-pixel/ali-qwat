<?php

namespace Tests\Feature\Admin\Reports;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitAndLossApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-09 12:00:00');
        $this->travelTo('2026-11-09 12:00:00');
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    /**
     * A general invoice of that type on that day, with one line of that price.
     */
    private function invoice(InvoiceType $type, string $date, float $price): InvoiceItem
    {
        $invoice = Invoice::factory()->create(['type' => $type, 'date' => $date]);

        return InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'price' => $price]);
    }

    /**
     * EN: Without a month the report opens on the month that just ended, with its name and the twelve month names for the picker.
     * AR: بدون شهر التقرير يفتح على الشهر اللي خلص، ويا اسمه وأسماء الأشهر الاثنعش للمنتقي.
     */
    public function test_it_opens_on_the_month_that_just_ended(): void
    {
        $this->actingAs($this->user())->getJson('/admin-api/reports/profit-and-loss')
            ->assertOk()
            ->assertJsonPath('month', '2026-10')
            ->assertJsonPath('current_month', '2026-11')
            ->assertJsonCount(12, 'month_names')
            ->assertJsonCount(4, 'cards')
            ->assertJsonPath('summary.checks_itself', true);
    }

    /**
     * EN: A month that has not begun, one before the first year, or one that is not a month is refused.
     * AR: الشهر اللي بعده ما بدا، أو قبل أول سنة، أو اللي مو شهر، ينرفض.
     */
    public function test_an_invalid_month_is_refused(): void
    {
        $this->actingAs($this->user());

        $this->getJson('/admin-api/reports/profit-and-loss?month=2026-12')->assertJsonValidationErrors('month');
        $this->getJson('/admin-api/reports/profit-and-loss?month=2025-12')->assertJsonValidationErrors('month');
        $this->getJson('/admin-api/reports/profit-and-loss?month=2026-13')->assertJsonValidationErrors('month');
        $this->getJson('/admin-api/reports/profit-and-loss?month=2026-11')->assertOk();
    }

    /**
     * EN: The revenues and the expenses of the month are listed, each expense description with its lines and the link to their invoice, and the net profit is revenue minus expenses.
     * AR: إيرادات ومصاريف الشهر تنعرض، كل وصف مصروف ويا بنوده ورابط فاتورتها، وصافي الربح = الإيرادات ناقص المصاريف.
     */
    public function test_the_statement_of_the_month(): void
    {
        $revenue = $this->invoice(InvoiceType::Input, '2026-10-05', 700);
        $expense = $this->invoice(InvoiceType::Output, '2026-10-06', 300);
        $this->invoice(InvoiceType::Output, '2026-09-30', 9999);

        $response = $this->actingAs($this->user())->getJson('/admin-api/reports/profit-and-loss?month=2026-10')
            ->assertOk()
            ->assertJsonCount(1, 'revenue.external')
            ->assertJsonPath('revenue.external.0.id', $revenue->specific_description_id)
            ->assertJsonCount(1, 'expenses.groups')
            ->assertJsonCount(1, 'expenses.groups.0.lines')
            ->assertJsonPath('expenses.groups.0.lines.0.invoice_id', $expense->invoice_id)
            ->assertJsonPath('expenses.groups.0.lines.0.invoice_path', "/general-invoices/{$expense->invoice_id}");

        $this->assertEquals(700, $response->json('revenue.total'));
        $this->assertEquals(300, $response->json('expenses.total'));
        $this->assertEquals(400, $response->json('summary.net_profit'));
        $this->assertSame('2026-09-30', substr($response->json('summary.value_at_start.moment'), 0, 10));
        $this->assertCount(6, $response->json('summary.value_at_start.parts'));
    }

    /**
     * EN: A left out description stays in the list, marked, but the totals skip it and matching is not checked.
     * AR: الوصف المستبعد يبقى بالقائمة ومعلّم، بس المجاميع تتجاوزه والمطابقة ما تنفحص.
     */
    public function test_exclusions_are_skipped_by_the_totals(): void
    {
        $expense = $this->invoice(InvoiceType::Output, '2026-10-06', 300);
        $groupId = $expense->invoice->general_description_id;

        $response = $this->actingAs($this->user())
            ->getJson("/admin-api/reports/profit-and-loss?month=2026-10&excluded_expenses[]={$groupId}")
            ->assertOk()
            ->assertJsonPath('expenses.groups.0.is_excluded', true)
            ->assertJsonPath('has_exclusions', true)
            ->assertJsonPath('summary.checks_itself', false);

        $this->assertEquals(0, $response->json('expenses.total'));
    }

    /**
     * EN: The report reads its sums in a fixed, small number of queries, however many lines the month has.
     * AR: التقرير يقرا مجاميعه بعدد قليل وثابت من الاستعلامات، مهما كثرت بنود الشهر.
     */
    public function test_the_query_count_stays_small(): void
    {
        foreach (range(1, 5) as $day) {
            $this->invoice(InvoiceType::Output, "2026-10-0{$day}", 100);
            $this->invoice(InvoiceType::Input, "2026-10-0{$day}", 100);
        }

        $this->actingAs($this->user());
        DB::enableQueryLog();
        $this->getJson('/admin-api/reports/profit-and-loss?month=2026-10')->assertOk();

        $this->assertLessThanOrEqual(40, count(DB::getQueryLog()));
    }

    /**
     * EN: The month downloads as an Excel file.
     * AR: الشهر ينزل كملف إكسل.
     */
    public function test_the_month_downloads_as_excel(): void
    {
        $response = $this->actingAs($this->user())->get('/admin-api/reports/profit-and-loss/excel?month=2026-10')->assertOk();

        $this->assertStringContainsString('profit-and-loss_2026-10-01_2026-10-31.xlsx', (string) $response->headers->get('Content-Disposition'));
    }
}
