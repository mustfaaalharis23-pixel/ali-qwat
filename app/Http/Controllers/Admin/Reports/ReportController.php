<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Supplier;
use App\Reports\GoldenReport;
use App\Reports\ItemSalesReport;
use App\Reports\PurchasesReport;
use App\Reports\Report;
use App\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The period reports of the admin application: sales, purchases, item sales, and the golden report.
 *
 * Every report is its own class in app/Reports, so the rows and the totals are worked out there.
 * A report opens on the last three months until today, a period is a single day when it has no end, and a day after
 * today is never asked for. Who may open a report is what the report allows (canAccess).
 */
class ReportController extends Controller
{
    /**
     * The reports by their address.
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
     * How many months back a report opens on.
     */
    private const DEFAULT_MONTHS = 3;

    /**
     * The rows, the totals, and the filters of one report in the asked period.
     */
    public function show(Request $request, string $report): JsonResponse
    {
        [$opened, $period, $filters] = $this->open($request, $report);

        $rows = $opened->fetchedRows();
        $payload = [
            'key' => $report,
            'title' => $opened::label(),
            'from' => $period['from'],
            'to' => $period['to'],
            'filters' => $filters,
            'columns' => $opened->columns(),
            'rows' => $rows,
            'totals' => $opened->hasTotalsCards() ? $opened->formattedTotals() : [],
            'excel_url' => route('admin-api.reports.excel', ['report' => $report, ...$period, ...$filters]),
        ];

        if ($opened instanceof GoldenReport) {
            $payload += $this->goldenSummary($opened->summary());
        }

        return response()->json($payload);
    }

    /**
     * The asked period of one report as an Excel file.
     */
    public function excel(Request $request, string $report): StreamedResponse
    {
        [$opened] = $this->open($request, $report);

        return $opened->download();
    }

    /**
     * The items bought from one supplier inside the asked period, as the purchases report opens them.
     */
    public function supplierItems(Request $request, int $supplier): JsonResponse
    {
        /** @var PurchasesReport $opened */
        [$opened] = $this->open($request, 'purchases');
        $name = Supplier::withTrashed()->findOrFail($supplier)->name_ar;

        $items = $opened->itemsBoughtFrom($supplier);
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
     * @return array{0: Report, 1: array{from: string, to: string}, 2: array<string, mixed>}
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

        return [$class::open($from, $to, $filters), ['from' => $from, 'to' => $to], $filters];
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
     * A report's own filters with their defaults: the most sold quantity, and every list with ten items each.
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
     * The summary line of the golden report, as numbers and as the texts shown.
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
