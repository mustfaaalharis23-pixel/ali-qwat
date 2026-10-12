{{--
    What a value of the company is made of, opened under its row in the profit and loss summary: every part with the
    amount it adds, then the value itself. What is owed to the suppliers is shown with a minus, because it is taken off
    the value.
    It uses logical alignment and Filament's color variables, so it follows the panel's dark mode and right-to-left layout.
--}}
@php
    use App\Metrics\Currency;
    use App\Metrics\FormattedValue;

    $line = 'border-bottom: 1px solid color-mix(in oklab, var(--gray-500) 25%, transparent)';
    $cell = 'padding: 0.625rem 0.75rem';

    $parts = [
        ['label' => __('filament/reports/profit_and_loss.value.stock'), 'amount' => $value->stockValue],
        ['label' => __('filament/reports/profit_and_loss.value.unconfirmed_purchases'), 'amount' => $value->unconfirmedPurchases],
        ['label' => __('filament/reports/profit_and_loss.value.supplier_balance'), 'amount' => -$value->supplierBalance],
        ['label' => __('filament/reports/profit_and_loss.value.accountant_box'), 'amount' => $value->accountantBox],
        ['label' => __('filament/reports/profit_and_loss.value.cash_sales_box'), 'amount' => $value->cashSalesBox],
        ['label' => __('filament/reports/profit_and_loss.value.postpaid_sales_box'), 'amount' => $value->postpaidSalesBox],
    ];

    $total = FormattedValue::of($value->total(), Currency::IraqiDinar);
@endphp

<div style="display: flex; flex-direction: column; gap: 0.75rem">
    <p class="fi-wi-stats-overview-stat-label">
        {{ __('filament/reports/profit_and_loss.value.moment', ['moment' => $value->moment->format('Y-m-d')]) }}
    </p>

    <div style="overflow-x: auto">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem">
            <tbody>
                @foreach ($parts as $part)
                    @php
                        $amount = FormattedValue::of($part['amount'], Currency::IraqiDinar);
                    @endphp

                    <tr style="{{ $line }}">
                        <td style="{{ $cell }}; text-align: start">{{ $part['label'] }}</td>
                        <td style="{{ $cell }}; text-align: end; color: var(--{{ $part['amount'] < 0 ? 'danger' : 'gray' }}-500)">
                            {{ $amount->number }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <td style="{{ $cell }}; text-align: start; font-weight: 700">
                        {{ __('filament/reports/profit_and_loss.value.total') }}
                    </td>
                    <td style="{{ $cell }}; text-align: end; font-weight: 700; color: var(--info-500)">
                        {{ $total->number }} {{ $total->currency }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
