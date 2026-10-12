{{--
    The last line of the golden report's table, inside the table itself: the profit of the whole period, how much of it
    these items are, and the sales under it in small letters. It is one quiet line in the middle, not a card.
--}}
@php
    use App\Metrics\Currency;
    use App\Metrics\FormattedValue;

    $percent = __('filament/reports/golden.percent');

    $periodProfit = FormattedValue::of($summary['period_profit'], Currency::IraqiDinar);
    $itemsProfit = FormattedValue::of($summary['items_profit'], Currency::IraqiDinar);
    $periodSales = FormattedValue::of($summary['period_sales'], Currency::IraqiDinar);
    $itemsSales = FormattedValue::of($summary['items_sales'], Currency::IraqiDinar);
    $profitShare = FormattedValue::of(round($summary['profit_share'], 1));
    $salesShare = FormattedValue::of(round($summary['sales_share'], 1));
    $itemsMargin = FormattedValue::of(round($summary['items_margin'], 1));
@endphp

<td colspan="{{ count($columns) }}" style="padding: 0.875rem 1rem; text-align: center">
    <div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: center; column-gap: 0.5rem; row-gap: 0.25rem; font-size: 0.95rem">
        <span class="fi-color-gray">{{ __('filament/reports/golden.summary.period_profit') }}</span>

        <span style="font-weight: 700; color: var(--success-500)">
            {{ $periodProfit->number }} {{ $periodProfit->currency }}
        </span>

        <span class="fi-color-gray">·</span>

        <span class="fi-color-gray">{{ __('filament/reports/golden.summary.profit_share') }}</span>

        <span style="font-weight: 700; color: var(--warning-500)">
            {{ $profitShare->number }}{{ $percent }}
        </span>

        <span class="fi-color-gray">({{ $itemsProfit->number }} {{ $itemsProfit->currency }})</span>
    </div>

    <div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: center; column-gap: 0.5rem; row-gap: 0.25rem; margin-top: 0.35rem; font-size: 0.8rem; opacity: 0.75">
        <span>{{ __('filament/reports/golden.summary.period_sales') }} {{ $periodSales->number }} {{ $periodSales->currency }}</span>
        <span>·</span>
        <span>{{ __('filament/reports/golden.summary.items_sales') }} {{ $itemsSales->number }} ({{ $salesShare->number }}{{ $percent }})</span>
        <span>·</span>
        <span>{{ __('filament/reports/golden.summary.items_margin') }} {{ $itemsMargin->number }}{{ $percent }}</span>
    </div>
</td>
