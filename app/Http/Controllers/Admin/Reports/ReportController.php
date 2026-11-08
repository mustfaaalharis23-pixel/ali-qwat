<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Filament\Reports\Golden\GoldenReport;
use App\Filament\Reports\ItemSales\ItemSalesReport;
use App\Filament\Reports\Purchases\PurchasesReport;
use App\Filament\Reports\Report;
use App\Filament\Reports\Sales\SalesReport;
use App\Http\Controllers\Controller;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The period reports of the admin application: sales, purchases, item sales, and the golden report.
 *
 * Every report is the panel's own report class run by ReportRunner, so the rows and the totals are worked out there.
 * A report opens on the last three months until today, a period is a single day when it has no end, and a day after
 * today is never asked for, like the panel's period field. Who may open a report is what the panel's page allows.
 */
class ReportController extends Controller
{
    /**
     * The reports by their address, which is the panel's own.
     *
     * @var array<string, class-string<Report>>
     */
    public const REPORTS = [
        'sales' => SalesReport::class,
        'purchases' => PurchasesReport::class,
        'item-sales' => ItemSalesReport::class,
        'golden' => GoldenReport::class,
    ];

    /**
     * How many months back a report opens on, as in the panel.
     */
    private const DEFAULT_MONTHS = 3;

    /**
     * The rows, the totals, and the filters of one report in the asked period.
     */
    public function show(Request $request, string $report): JsonResponse
    {
        [$runner, $period, $filters] = $this->open($request, $report);

        $rows = $runner->rows();
        $payload = [
            'key' => $report,
            'title' => $runner->report::label(),
            'from' => $period['from'],
            'to' => $period['to'],
            'filters' => $filters,
            'columns' => $runner->columns(),
            'rows' => $rows,
            'totals' => $runner->hasTotalsCards() ? $runner->formattedTotals() : [],
            'excel_url' => route('admin-api.reports.excel', ['report' => $report, ...$period, ...$filters]),
        ];

        if ($runner->report instanceof GoldenReport) {
            $payload += $this->goldenSummary($runner->report->summary());
        }

        return response()->json($payload);
    }

    /**
     * The asked period of one report as an Excel file.
     */
    public function excel(Request $request, string $report): StreamedResponse
    {
        [$runner] = $this->open($request, $report);

        return $runner->download();
    }

    /**
     * The items bought from one supplier inside the asked period, as the purchases report opens them.
     */
    public function supplierItems(Request $request, int $supplier): JsonResponse
    {
        [$runner] = $this->open($request, 'purchases');
        $name = Supplier::withTrashed()->findOrFail($supplier)->name_ar;

        /** @var list<array{name: string, quantity: float, amount: float}> $items */
        $items = $runner->call('itemsBoughtFrom', $supplier);
        $total = (float) array_sum(array_column($items, 'amount'));

        return response()->json([
            'heading' => __('filament/reports/purchases.items.heading', ['supplier' => $name]),
            'items' => $items,
            'total' => $total,
            'total_text' => $this->money($total, Currency::ChineseYuan),
        ]);
    }

    /**
     * The report run on the asked period with its validated filters, after checking the user may open it.
     *
     * @return array{0: ReportRunner<Report>, 1: array{from: string, to: string}, 2: array<string, mixed>}
     */
    private function open(Request $request, string $report): array
    {
        $class = self::REPORTS[$report] ?? abort(404);

        abort_unless($class::canAccess(), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            ...$this->filterRules($report),
        ]);

        $today = CarbonImmutable::today();
        $from = $validated['from'] ?? $today->subMonths(self::DEFAULT_MONTHS)->toDateString();
        $to = $validated['to'] ?? (isset($validated['from']) ? $from : $today->toDateString());
        $filters = $this->filters($report, $validated);

        return [ReportRunner::open($class, $from, $to, $filters), ['from' => $from, 'to' => $to], $filters];
    }

    /**
     * The rules of a report's own filters.
     *
     * @return array<string, list<string>>
     */
    private function filterRules(string $report): array
    {
        return match ($report) {
            'item-sales' => ['sort' => ['nullable', 'in:quantity,amount']],
            'golden' => [
                'lists' => ['nullable', 'array'],
                'lists.*' => ['in:profit,quantity,amount'],
                'count' => ['nullable', 'integer', 'min:1', 'max:100'],
            ],
            default => [],
        };
    }

    /**
     * A report's own filters with the panel's defaults: the most sold quantity, and every list with ten items each.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function filters(string $report, array $validated): array
    {
        return match ($report) {
            'item-sales' => ['sort' => $validated['sort'] ?? 'quantity'],
            'golden' => [
                'lists' => array_values(array_intersect(['profit', 'quantity', 'amount'], $validated['lists'] ?? [])) ?: ['profit', 'quantity', 'amount'],
                'count' => (int) ($validated['count'] ?? 10),
            ],
            default => [],
        };
    }

    /**
     * The summary line of the golden report, as numbers and as the texts the panel shows.
     *
     * @param  array<string, float>|null  $summary
     * @return array{summary: array<string, float>|null, summary_text: array<string, string>|null}
     */
    private function goldenSummary(?array $summary): array
    {
        if ($summary === null) {
            return ['summary' => null, 'summary_text' => null];
        }

        $percent = __('filament/reports/golden.percent');
        $share = fn (float $value): string => FormattedValue::of(round($value, 1))->number.$percent;

        return [
            'summary' => $summary,
            'summary_text' => [
                'period_profit' => $this->money($summary['period_profit']),
                'items_profit' => $this->money($summary['items_profit']),
                'period_sales' => $this->money($summary['period_sales']),
                'items_sales' => FormattedValue::of($summary['items_sales'], Currency::IraqiDinar)->number,
                'profit_share' => $share($summary['profit_share']),
                'sales_share' => $share($summary['sales_share']),
                'items_margin' => $share($summary['items_margin']),
            ],
        ];
    }

    private function money(float $value, Currency $currency = Currency::IraqiDinar): string
    {
        $formatted = FormattedValue::of($value, $currency);

        return trim("{$formatted->number} {$formatted->currency}");
    }
}
