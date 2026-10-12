{{--
    The items bought from one supplier in the purchases report: each item with its bought quantity and amount, then their total.
    It uses logical alignment and Filament's color variables, so it follows the panel's dark mode and right-to-left layout.
--}}
@php
    use App\Metrics\Currency;
    use App\Metrics\FormattedValue;

    $line = 'border-bottom: 1px solid color-mix(in oklab, var(--gray-500) 25%, transparent)';
    $cell = 'padding: 0.625rem 0.75rem';
@endphp

@if ($items === [])
    <p class="fi-wi-stats-overview-stat-label">
        {{ __('filament/reports.empty.no_data') }}
    </p>
@else
    <div style="overflow-x: auto">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem">
            <thead>
                <tr style="{{ $line }}">
                    <th style="{{ $cell }}; text-align: start; font-weight: 600">
                        {{ __('filament/reports/purchases.items.item') }}
                    </th>
                    <th style="{{ $cell }}; text-align: end; font-weight: 600">
                        {{ __('filament/reports/purchases.items.quantity') }}
                    </th>
                    <th style="{{ $cell }}; text-align: end; font-weight: 600">
                        {{ __('filament/reports/purchases.items.amount') }}
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($items as $item)
                    <tr style="{{ $line }}">
                        <td style="{{ $cell }}; text-align: start">{{ $item['name'] }}</td>
                        <td style="{{ $cell }}; text-align: end">{{ FormattedValue::of($item['quantity'])->number }}</td>
                        <td style="{{ $cell }}; text-align: end">{{ FormattedValue::of($item['amount'], Currency::ChineseYuan)->number }}</td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                @php
                    $total = FormattedValue::of(array_sum(array_column($items, 'amount')), Currency::ChineseYuan);
                @endphp

                <tr>
                    <td colspan="2" style="{{ $cell }}; text-align: start; font-weight: 700">
                        {{ __('filament/reports/purchases.items.total') }}
                    </td>
                    <td style="{{ $cell }}; text-align: end; font-weight: 700; color: var(--primary-500)">
                        {{ $total->number }} {{ $total->currency }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
