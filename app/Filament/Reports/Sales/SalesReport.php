<?php

namespace App\Filament\Reports\Sales;

use App\Enums\OrderPaymentType;
use App\Filament\Reports\Report;
use App\Metrics\Currency;
use App\Metrics\Period;
use App\Models\Order;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Collection;

/**
 * The sales report: one row per day with sales, showing its cash sales, its postpaid sales, and their total,
 * and under the table the totals of the period. The sales are the orders' total amounts, so a discount is counted.
 * Deleted orders are not counted.
 */
class SalesReport extends Report
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    public static function uriKey(): string
    {
        return 'sales';
    }

    public static function label(): string
    {
        return __('filament/reports/sales.title');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('day')
                ->label(__('filament/reports/sales.columns.day')),
            TextColumn::make('cash')
                ->label(OrderPaymentType::Cash->getLabel())
                ->numeric()
                ->color(OrderPaymentType::Cash->getColor()),
            TextColumn::make('postpaid')
                ->label(OrderPaymentType::Postpaid->getLabel())
                ->numeric()
                ->color(OrderPaymentType::Postpaid->getColor()),
            TextColumn::make('total')
                ->label(__('filament/reports/sales.columns.total'))
                ->numeric()
                ->weight(FontWeight::Bold),
        ];
    }

    /**
     * The day is shown as day/month; the year is added only when the period crosses years.
     */
    public function rows(Period $period): array
    {
        $dayFormat = $period->start->year === $period->end->year ? 'd/m' : 'd/m/Y';

        return $period->applyTo(Order::query(), 'date')
            ->toBase()
            ->selectRaw('date, payment_type, sum(total_amount) as sales')
            ->groupBy('date', 'payment_type')
            ->orderBy('date')
            ->get()
            ->groupBy(fn (object $sale): string => CarbonImmutable::parse($sale->date)->toDateString())
            ->map(function (Collection $daySales, string $day) use ($dayFormat): array {
                $cash = (float) $daySales->firstWhere('payment_type', OrderPaymentType::Cash->value)?->sales;
                $postpaid = (float) $daySales->firstWhere('payment_type', OrderPaymentType::Postpaid->value)?->sales;

                return [
                    'day' => CarbonImmutable::parse($day)->format($dayFormat),
                    'cash' => $cash,
                    'postpaid' => $postpaid,
                    'total' => $cash + $postpaid,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The cash total, the postpaid total, and both together, in the colors of their payment types.
     */
    public function totals(array $rows): array
    {
        $rows = collect($rows);

        return [
            [
                'label' => __('filament/reports/sales.totals.cash'),
                'value' => $rows->sum('cash'),
                'color' => OrderPaymentType::Cash->getColor(),
                'currency' => Currency::IraqiDinar,
            ],
            [
                'label' => __('filament/reports/sales.totals.postpaid'),
                'value' => $rows->sum('postpaid'),
                'color' => OrderPaymentType::Postpaid->getColor(),
                'currency' => Currency::IraqiDinar,
            ],
            [
                'label' => __('filament/reports/sales.totals.total'),
                'value' => $rows->sum('total'),
                'color' => 'primary',
                'currency' => Currency::IraqiDinar,
            ],
        ];
    }
}
