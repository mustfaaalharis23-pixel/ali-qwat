{{--
    The profit and loss statement: the revenue and the expenses side by side, then the summary with the proof.

    How it is read: the names sit at the start of the line and the amounts at its end, in one monospace column,
    so the sizes can be compared by eye. A negative amount is between brackets, the way an accountant writes it.
    An expense description opens its own lines in place, each with the invoice it came from as a link, so any total
    can be walked down to the single invoice behind it. The two company value rows open what they are made of the
    same way. Opening and closing happens in the browser, so nothing is fetched again.

    The box at the start of a line takes it out of the totals, or puts it back. What is taken out is not hidden:
    it stays on its line, crossed out and marked, so no one reads a total without seeing what is missing from it.
    While anything is taken out, the summary says that matching is not checked, because the profit is then of a part
    of the month while the value of the company is of the whole of it.

    Every color is a Filament color variable mixed into the page, so the statement follows the panel's dark mode,
    and every side is logical, so it follows the right-to-left layout.
--}}
@php
    use App\Metrics\Currency;
    use App\Metrics\FormattedValue;

    $currency = Currency::IraqiDinar;
    $isRightToLeft = __('filament-panels::layout.direction') === 'rtl';
    $closedArrow = $isRightToLeft ? '◀' : '▶';

    /** An amount the way an accountant reads it: a whole dinar, and a negative between brackets. */
    $money = function (?float $amount) use ($currency): ?string {
        if ($amount === null) {
            return null;
        }

        $number = FormattedValue::of(abs($amount), $currency)->number;

        return $amount < 0 ? "({$number})" : $number;
    };

    $card = 'border: 1px solid color-mix(in oklab, var(--gray-500) 22%, transparent); border-radius: 0.75rem; overflow: hidden';
    $header = fn (string $color): string => "padding: 0.875rem 1rem; background: color-mix(in oklab, var(--{$color}-500) 12%, transparent); border-bottom: 2px solid var(--{$color}-500); display: flex; align-items: baseline; justify-content: space-between; gap: 0.5rem";
    $headerTitle = 'font-size: 1.05rem; font-weight: 700';
    $headerUnit = 'font-size: 0.75rem; opacity: 0.7';
    $line = 'border-top: 1px solid color-mix(in oklab, var(--gray-500) 15%, transparent)';
    $cell = 'padding: 0.625rem 1rem';
    $childCell = 'padding: 0.5rem 1rem';
    $boxCell = 'width: 2.75rem; text-align: center; padding: 0.5rem 0 0.5rem 0.625rem';
    $number = "font-family: 'Courier New', monospace; text-align: end; white-space: nowrap";
    $empty = 'padding: 1.5rem 1rem; text-align: center; color: var(--gray-500); font-size: 0.875rem';
    $badge = 'font-size: 0.7rem; font-weight: 700; padding: 0.05rem 0.4rem; border-radius: 999px; background: color-mix(in oklab, var(--gray-500) 20%, transparent); opacity: 0.85';
    $excludedBadge = 'font-size: 0.68rem; font-weight: 700; padding: 0.05rem 0.45rem; border-radius: 999px; background: color-mix(in oklab, var(--warning-500) 22%, transparent); color: var(--warning-500); margin-inline-start: 0.35rem';
    $invoiceBadge = 'font-size: 0.72rem; font-weight: 600; padding: 0.1rem 0.45rem; border-radius: 0.375rem; background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-500); margin-inline-start: 0.35rem; white-space: nowrap';
    $arrow = 'display: inline-block; width: 1rem; font-size: 0.7rem; opacity: 0.6';
    $child = 'background: color-mix(in oklab, var(--gray-500) 7%, transparent)';
    $crossed = 'opacity: 0.5; text-decoration: line-through';
@endphp

<style>
    /* Alpine hides the closed rows only after it starts, so they are hidden from the first paint. */
    .pnl [x-cloak] { display: none !important; }
    .pnl-openable:hover { background: color-mix(in oklab, var(--gray-500) 10%, transparent); }
    .pnl-box { transform: scale(1.1); cursor: pointer; }
    .pnl-invoice:hover { background: color-mix(in oklab, var(--primary-500) 32%, transparent); }
</style>

@if ($statement === null)
    <div style="{{ $card }}">
        <div style="{{ $empty }}">
            {{ __('filament/reports.empty.not_fetched') }}
        </div>
    </div>
@else
    <div class="pnl" style="display: flex; flex-direction: column; gap: 1rem">
        <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr))">
            {{-- The revenue: the profit the sales froze, then every external revenue. --}}
            <div style="{{ $card }}">
                <div style="{{ $header('success') }}">
                    <span style="{{ $headerTitle }}; color: var(--success-500)">
                        {{ __('filament/reports/profit_and_loss.sections.revenue') }}
                    </span>
                    <span style="{{ $headerUnit }}">{{ $currency->symbol() }}</span>
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem">
                    <tbody>
                        <tr>
                            <td style="{{ $boxCell }}"></td>
                            <td style="{{ $cell }}; text-align: start">
                                {{ __('filament/reports/profit_and_loss.rows.sales_profit') }}
                            </td>
                            <td style="{{ $cell }}; {{ $number }}; color: var(--success-500)">
                                {{ $money($statement->salesProfit) }}
                            </td>
                        </tr>

                        @foreach ($statement->externalRevenues as $externalRevenue)
                            <tr style="{{ $line }}">
                                <td style="{{ $boxCell }}">
                                    <input
                                        type="checkbox"
                                        class="pnl-box"
                                        wire:change="toggleRevenue({{ $externalRevenue['id'] }})"
                                        @checked(! $externalRevenue['isExcluded'])
                                        aria-label="{{ __('filament/reports/profit_and_loss.exclusions.include', ['name' => $externalRevenue['name']]) }}"
                                    >
                                </td>
                                <td style="{{ $cell }}; text-align: start">
                                    <span @style([$crossed => $externalRevenue['isExcluded']])>{{ $externalRevenue['name'] }}</span>
                                    @if ($externalRevenue['isExcluded'])
                                        <span style="{{ $excludedBadge }}">{{ __('filament/reports/profit_and_loss.exclusions.excluded') }}</span>
                                    @endif
                                </td>
                                <td style="{{ $cell }}; {{ $number }}; color: var(--{{ $externalRevenue['isExcluded'] ? 'gray' : 'success' }}-500){{ $externalRevenue['isExcluded'] ? '; '.$crossed : '' }}">
                                    {{ $money($externalRevenue['amount']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr style="border-top: 2px solid color-mix(in oklab, var(--success-500) 40%, transparent)">
                            <td style="{{ $boxCell }}"></td>
                            <td style="{{ $cell }}; text-align: start; font-weight: 700">
                                {{ __('filament/reports/profit_and_loss.rows.total_revenue') }}
                            </td>
                            <td style="{{ $cell }}; {{ $number }}; font-weight: 700; font-size: 1rem; color: var(--success-500)">
                                {{ $money($statement->totalRevenue()) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- The expenses: one row per general description, and its own lines under it when it is opened. --}}
            <div style="{{ $card }}" x-data="{ open: [] }">
                <div style="{{ $header('danger') }}">
                    <span style="{{ $headerTitle }}; color: var(--danger-500)">
                        {{ __('filament/reports/profit_and_loss.sections.expenses') }}
                    </span>
                    <span style="{{ $headerUnit }}">{{ $currency->symbol() }}</span>
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem">
                    <tbody>
                        @forelse ($statement->expenseGroups as $group)
                            @php
                                $lines = $statement->linesOf($group['id']);
                            @endphp

                            <tr style="{{ $loop->first ? '' : $line }}">
                                <td style="{{ $boxCell }}">
                                    <input
                                        type="checkbox"
                                        class="pnl-box"
                                        wire:change="toggleExpense({{ $group['id'] }})"
                                        @checked(! $group['isExcluded'])
                                        aria-label="{{ __('filament/reports/profit_and_loss.exclusions.include', ['name' => $group['name']]) }}"
                                    >
                                </td>
                                <td
                                    class="pnl-openable"
                                    style="{{ $cell }}; text-align: start; font-weight: 600; cursor: pointer"
                                    x-on:click="open.includes({{ $group['id'] }}) ? open = open.filter((id) => id !== {{ $group['id'] }}) : open.push({{ $group['id'] }})"
                                >
                                    <span style="{{ $arrow }}">
                                        <span x-show="! open.includes({{ $group['id'] }})">{{ $closedArrow }}</span>
                                        <span x-show="open.includes({{ $group['id'] }})" x-cloak>▼</span>
                                    </span>
                                    <span @style([$crossed => $group['isExcluded']])>{{ $group['name'] }}</span>
                                    <span style="{{ $badge }}">{{ count($lines) }}</span>
                                    @if ($group['isExcluded'])
                                        <span style="{{ $excludedBadge }}">{{ __('filament/reports/profit_and_loss.exclusions.excluded') }}</span>
                                    @endif
                                </td>
                                <td style="{{ $cell }}; {{ $number }}; font-weight: 600; color: var(--{{ $group['isExcluded'] ? 'gray' : 'danger' }}-500){{ $group['isExcluded'] ? '; '.$crossed : '' }}">
                                    {{ $money($group['amount']) }}
                                </td>
                            </tr>

                            @foreach ($lines as $lineOfGroup)
                                <tr style="{{ $line }}; {{ $child }}" x-show="open.includes({{ $group['id'] }})" x-cloak>
                                    <td style="{{ $boxCell }}"></td>
                                    <td style="{{ $childCell }}; text-align: start; padding-inline-start: 2.5rem; font-size: 0.85rem">
                                        <span style="opacity: 0.4">└</span>
                                        {{ $lineOfGroup['name'] }}
                                        <a
                                            class="pnl-invoice"
                                            style="{{ $invoiceBadge }}"
                                            href="{{ $report->invoiceUrl($lineOfGroup['invoice_id']) }}"
                                            target="_blank"
                                            title="{{ $lineOfGroup['invoice_name'] }}"
                                        >
                                            {{ __('filament/reports/profit_and_loss.rows.invoice') }} #{{ $lineOfGroup['invoice_id'] }}
                                        </a>
                                        <span style="opacity: 0.55; font-size: 0.75rem; margin-inline-start: 0.35rem; font-family: 'Courier New', monospace">
                                            {{ $lineOfGroup['invoice_date'] }}
                                        </span>
                                    </td>
                                    <td style="{{ $childCell }}; {{ $number }}; font-size: 0.85rem; opacity: 0.85">
                                        {{ $money($lineOfGroup['amount']) }}
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="3" style="{{ $empty }}">{{ __('filament/reports.empty.no_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr style="border-top: 2px solid color-mix(in oklab, var(--danger-500) 40%, transparent)">
                            <td style="{{ $boxCell }}"></td>
                            <td style="{{ $cell }}; text-align: start; font-weight: 700">
                                {{ __('filament/reports/profit_and_loss.rows.total_expenses') }}
                            </td>
                            <td style="{{ $cell }}; {{ $number }}; font-weight: 700; font-size: 1rem; color: var(--danger-500)">
                                {{ $money($statement->totalExpenses()) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- The summary: what the period earned, and the proof against what the company is really worth. --}}
        <div style="{{ $card }}" x-data="{ value: null }">
            <div style="{{ $header('info') }}">
                <span style="{{ $headerTitle }}; color: var(--info-500)">
                    {{ __('filament/reports/profit_and_loss.sections.summary') }}
                </span>

                <span style="display: flex; align-items: baseline; gap: 0.5rem">
                    @unless ($statement->checksItself())
                        <span style="{{ $excludedBadge }}">{{ __('filament/reports/profit_and_loss.exclusions.after') }}</span>
                    @endunless
                    <span style="{{ $headerUnit }}">{{ $currency->symbol() }}</span>
                </span>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem">
                <tbody>
                    <tr>
                        <td style="{{ $cell }}; text-align: start; font-weight: 700">
                            {{ __('filament/reports/profit_and_loss.rows.net_profit') }}
                            @unless ($statement->checksItself())
                                <span style="{{ $excludedBadge }}">{{ __('filament/reports/profit_and_loss.exclusions.after') }}</span>
                            @endunless
                        </td>
                        <td style="{{ $cell }}; {{ $number }}; font-weight: 700; font-size: 1.05rem; color: var(--{{ $statement->netProfit() < 0 ? 'danger' : 'success' }}-500)">
                            {{ $money($statement->netProfit()) }}
                        </td>
                    </tr>

                    <tr style="{{ $line }}">
                        <td style="{{ $cell }}; text-align: start">
                            {{ __('filament/reports/profit_and_loss.rows.capital_movement') }}
                        </td>
                        <td style="{{ $cell }}; {{ $number }}; opacity: 0.85">
                            {{ $money($statement->capitalMovement) }}
                        </td>
                    </tr>

                    @foreach ([['key' => 'start', 'label' => 'value_at_start', 'value' => $statement->valueAtStart], ['key' => 'computed', 'label' => 'computed_value', 'value' => null], ['key' => 'end', 'label' => 'value_at_end', 'value' => $statement->valueAtEnd]] as $valueRow)
                        @php
                            $isComputed = $valueRow['value'] === null;
                            $amount = $isComputed ? $statement->computedValue() : $valueRow['value']->total();
                        @endphp

                        <tr
                            @class(['pnl-openable' => ! $isComputed])
                            style="{{ $line }}{{ $isComputed ? '; background: color-mix(in oklab, var(--warning-500) 10%, transparent)' : '; cursor: pointer' }}"
                            @if (! $isComputed)
                                x-on:click="value = (value === '{{ $valueRow['key'] }}' ? null : '{{ $valueRow['key'] }}')"
                            @endif
                        >
                            <td style="{{ $cell }}; text-align: start{{ $isComputed ? '; font-weight: 700' : '' }}">
                                @unless ($isComputed)
                                    <span style="{{ $arrow }}">
                                        <span x-show="value !== '{{ $valueRow['key'] }}'">{{ $closedArrow }}</span>
                                        <span x-show="value === '{{ $valueRow['key'] }}'" x-cloak>▼</span>
                                    </span>
                                @endunless
                                {{ __("filament/reports/profit_and_loss.rows.{$valueRow['label']}") }}
                            </td>
                            <td style="{{ $cell }}; {{ $number }}; font-weight: {{ $isComputed ? 700 : 600 }}; color: var(--{{ $isComputed ? 'warning' : 'info' }}-500)">
                                {{ $money($amount) }}
                            </td>
                        </tr>

                        @unless ($isComputed)
                            <tr style="{{ $line }}; {{ $child }}" x-show="value === '{{ $valueRow['key'] }}'" x-cloak>
                                <td colspan="2" style="padding: 0.75rem 1rem 1rem; padding-inline-start: 2.5rem">
                                    @include('filament.reports.profit-and-loss.company-value', ['value' => $valueRow['value']])
                                </td>
                            </tr>
                        @endunless
                    @endforeach

                    {{-- The whole row is colored, so the state of the report is read from across the room.
                         While something is left out of the totals, the report does not judge itself at all. --}}
                    @php
                        $matchingColor = match (true) {
                            ! $statement->checksItself() => 'gray',
                            $statement->isMatched() => 'success',
                            default => 'danger',
                        };
                        $matchingText = $statement->checksItself()
                            ? __('filament/reports/profit_and_loss.rows.'.($statement->isMatched() ? 'matched' : 'not_matched'))
                            : __('filament/reports/profit_and_loss.exclusions.not_checked');
                    @endphp

                    <tr style="{{ $line }}; background: color-mix(in oklab, var(--{{ $matchingColor }}-500) 12%, transparent)">
                        <td style="{{ $cell }}; text-align: start; font-weight: 700; font-size: 1.05rem; color: var(--{{ $matchingColor }}-500)">
                            {{ __('filament/reports/profit_and_loss.rows.matching') }}
                        </td>
                        <td style="{{ $cell }}; text-align: end; font-weight: 700; font-size: 1.05rem; color: var(--{{ $matchingColor }}-500)">
                            {{ $matchingText }}
                        </td>
                    </tr>

                    <tr style="{{ $line }}">
                        <td style="{{ $cell }}; text-align: start; font-weight: 600">
                            {{ __('filament/reports/profit_and_loss.rows.difference') }}
                        </td>
                        <td style="{{ $cell }}; {{ $number }}; font-weight: 700; color: var(--{{ $matchingColor }}-500)">
                            {{ $money($statement->difference()) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif
