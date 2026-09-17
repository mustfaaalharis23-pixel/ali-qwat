{{--
    The totals of a report under its table: one card per total, its name on top and its colored amount under it.
    It uses Filament's stat card classes and color variables, so it follows the panel's dark mode and right-to-left layout.
--}}
@php
    use App\Metrics\FormattedValue;
@endphp

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1rem">
    @foreach ($totals as $total)
        @php
            $value = FormattedValue::of($total['value'], $total['currency'] ?? null);
        @endphp

        <div
            class="fi-wi-stats-overview-stat"
            style="border-top: 4px solid var(--{{ $total['color'] }}-500)"
        >
            <div class="fi-wi-stats-overview-stat-content" style="row-gap: 0.75rem">
                <div style="display: flex; align-items: center; gap: 0.5rem">
                    <span style="width: 0.625rem; height: 0.625rem; border-radius: 9999px; background: var(--{{ $total['color'] }}-500)"></span>

                    <span class="fi-wi-stats-overview-stat-label" style="font-size: 1rem">
                        {{ $total['label'] }}
                    </span>
                </div>

                <div style="display: flex; flex-wrap: wrap; align-items: baseline; column-gap: 0.5rem">
                    <span
                        class="fi-wi-stats-overview-stat-value"
                        style="font-size: 1.875rem; line-height: 1; color: var(--{{ $total['color'] }}-500)"
                    >
                        {{ $value->number }}
                    </span>

                    @if (filled($value->currency))
                        <span class="fi-wi-stats-overview-stat-label" style="font-size: 1rem">
                            {{ $value->currency }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
