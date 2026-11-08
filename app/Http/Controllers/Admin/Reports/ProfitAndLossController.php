<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Filament\Reports\ProfitAndLoss\ProfitAndLossReport;
use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
use App\Http\Controllers\Controller;
use App\Metrics\CompanyValue;
use App\Metrics\ProfitAndLoss;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ReflectionClassConstant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The profit and loss report of one whole month, with the descriptions the reader left out.
 *
 * The statement is App\Metrics\ProfitAndLoss, worked out once per request by the panel's report class: the sums of the
 * month and the two company values are read in a fixed number of queries, whatever the number of lines.
 */
class ProfitAndLossController extends Controller
{
    /**
     * The statement of the asked month, the cards above it, and what the month picker needs.
     */
    public function show(Request $request): JsonResponse
    {
        [$runner, $month] = $this->open($request);

        $report = $runner->report;
        $cards = $runner->formattedTotals();

        /** @var ProfitAndLoss $statement */
        $statement = $runner->call('fetchedStatement');
        $invoiceSlug = GeneralInvoiceResource::getSlug();

        return response()->json([
            'title' => $report::label(),
            'subheading' => $report->getSubheading(),
            'month' => $month,
            'current_month' => CarbonImmutable::now()->format('Y-m'),
            'first_year' => $this->firstYear(),
            'month_names' => $report->monthNames(),
            'has_exclusions' => $report->hasExclusions(),
            'excluded_revenues' => $report->excludedRevenues,
            'excluded_expenses' => $report->excludedExpenses,
            'cards' => $cards,
            'revenue' => [
                'sales_profit' => $statement->salesProfit,
                'external' => array_map(fn (array $revenue): array => [
                    'id' => $revenue['id'],
                    'name' => $revenue['name'],
                    'amount' => $revenue['amount'],
                    'is_excluded' => $revenue['isExcluded'],
                ], $statement->externalRevenues),
                'total' => $statement->totalRevenue(),
            ],
            'expenses' => [
                'groups' => array_map(fn (array $group): array => [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'amount' => $group['amount'],
                    'is_excluded' => $group['isExcluded'],
                    'lines' => array_map(fn (array $line): array => [
                        'name' => $line['name'],
                        'amount' => $line['amount'],
                        'invoice_id' => $line['invoice_id'],
                        'invoice_name' => $line['invoice_name'],
                        'invoice_date' => $line['invoice_date'],
                        'invoice_path' => "/{$invoiceSlug}/{$line['invoice_id']}",
                    ], $statement->linesOf($group['id'])),
                ], $statement->expenseGroups),
                'total' => $statement->totalExpenses(),
            ],
            'summary' => [
                'net_profit' => $statement->netProfit(),
                'capital_movement' => $statement->capitalMovement,
                'value_at_start' => $this->companyValue($statement->valueAtStart),
                'computed_value' => $statement->computedValue(),
                'value_at_end' => $this->companyValue($statement->valueAtEnd),
                'difference' => $statement->difference(),
                'checks_itself' => $statement->checksItself(),
                'is_matched' => $statement->checksItself() && $statement->isMatched(),
            ],
            'excel_url' => route('admin-api.reports.profit-and-loss.excel', array_filter([
                'month' => $month,
                'excluded_revenues' => $report->excludedRevenues,
                'excluded_expenses' => $report->excludedExpenses,
            ])),
        ]);
    }

    /**
     * The statement of the asked month as an Excel file.
     */
    public function excel(Request $request): StreamedResponse
    {
        [$runner] = $this->open($request);

        return $runner->download();
    }

    /**
     * The report run on the asked month, the month that just ended when none is asked, with its exclusions.
     * A month that has not begun, or one before the first year of the picker, is refused.
     *
     * @return array{0: ReportRunner<ProfitAndLossReport>, 1: string}
     */
    private function open(Request $request): array
    {
        abort_unless(ProfitAndLossReport::canAccess(), 403);

        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', function (string $attribute, mixed $value, Closure $fail): void {
                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value) !== 1) {
                    return;
                }

                $chosen = CarbonImmutable::parse($value.'-01');

                if ($chosen->greaterThan(CarbonImmutable::now()->startOfMonth()) || $chosen->year < $this->firstYear()) {
                    $fail(__('validation.in', ['attribute' => __('filament/reports/profit_and_loss.picker.label')]));
                }
            }],
            'excluded_revenues' => ['nullable', 'array'],
            'excluded_revenues.*' => ['integer'],
            'excluded_expenses' => ['nullable', 'array'],
            'excluded_expenses.*' => ['integer'],
        ]);

        $month = $validated['month'] ?? app(ProfitAndLossReport::class)->defaultMonth()->format('Y-m');
        $start = CarbonImmutable::parse($month.'-01');

        $runner = ReportRunner::open(ProfitAndLossReport::class, $start->startOfMonth()->toDateString(), $start->endOfMonth()->toDateString());
        $runner->report->month = $month;
        $runner->report->excludedRevenues = array_values(array_unique(array_map('intval', $validated['excluded_revenues'] ?? [])));
        $runner->report->excludedExpenses = array_values(array_unique(array_map('intval', $validated['excluded_expenses'] ?? [])));

        return [$runner, $month];
    }

    /**
     * The oldest year the month picker can go back to, as the panel's report says.
     */
    private function firstYear(): int
    {
        return (int) (new ReflectionClassConstant(ProfitAndLossReport::class, 'FIRST_YEAR'))->getValue();
    }

    /**
     * What a company value is made of, every part with the amount it adds, and the value itself.
     *
     * @return array{moment: string, parts: list<array{key: string, amount: float}>, total: float}
     */
    private function companyValue(CompanyValue $value): array
    {
        return [
            'moment' => $value->moment->format('Y-m-d'),
            'parts' => [
                ['key' => 'stock', 'amount' => $value->stockValue],
                ['key' => 'unconfirmed_purchases', 'amount' => $value->unconfirmedPurchases],
                ['key' => 'supplier_balance', 'amount' => -$value->supplierBalance],
                ['key' => 'accountant_box', 'amount' => $value->accountantBox],
                ['key' => 'cash_sales_box', 'amount' => $value->cashSalesBox],
                ['key' => 'postpaid_sales_box', 'amount' => $value->postpaidSalesBox],
            ],
            'total' => $value->total(),
        ];
    }
}
