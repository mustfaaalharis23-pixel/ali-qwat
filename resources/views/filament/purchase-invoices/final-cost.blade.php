{{--
    Where a purchase line's final cost of one dozen comes from, opened from under it on the invoice page.
    On top, the invoice's rates as small coloured tiles. Then a card for the goods and one for the transport, each a numbered list
    of steps: what the step is, the sum done with the line's own numbers under it, and what it comes to; the last step of each,
    in dinars, is tinted. At the bottom, a green card adds the two up to the final cost.
    Each part keeps one colour all through (the goods amber, the transport blue, the dinars green), taken from the panel's own
    colours, so it reads the same in light and dark mode and right to left.

    $rates: list of ['label' => string, 'value' => string, 'note' => ?string, 'color' => string, 'icon' => Heroicon]
    $sections: list of ['title' => string, 'color' => string, 'icon' => Heroicon, 'steps' => list of ['label' => string, 'math' => ?string, 'value' => string]]
    $parts: list of ['label' => string, 'value' => string, 'color' => string]
    $total: string
    $hasTransport: bool
--}}
@php
    $tint = fn (string $color, int $percent): string => "color-mix(in oklab, var(--{$color}-500) {$percent}%, transparent)";
    $tone = fn (string $color): string => "--tone: var(--{$color}-600); --tone-dark: var(--{$color}-400)";
@endphp

<style>
    .final-cost .tone { color: var(--tone); }
    .dark .final-cost .tone { color: var(--tone-dark); }
</style>

<div class="final-cost" style="display: grid; gap: 1rem; font-variant-numeric: tabular-nums">
    {{-- The invoice's rates. --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 0.625rem">
        @foreach ($rates as $rate)
            <div style="border-radius: 0.875rem; padding: 0.75rem 0.875rem; background: {{ $tint($rate['color'], 10) }}; box-shadow: inset 0 0 0 1px {{ $tint($rate['color'], 28) }}">
                <div class="tone" style="{{ $tone($rate['color']) }}; display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 600">
                    <x-filament::icon :icon="$rate['icon']" style="flex: none; width: 1rem; height: 1rem" />
                    <span>{{ $rate['label'] }}</span>
                </div>
                <div style="margin-top: 0.375rem; font-size: 1rem; font-weight: 700; white-space: nowrap">{{ $rate['value'] }}</div>
                @if ($rate['note'] !== null)
                    <div style="margin-top: 0.125rem; font-size: 0.75rem; opacity: 0.6">{{ $rate['note'] }}</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- The goods, then the transport, step by step. --}}
    @foreach ($sections as $section)
        @php($color = $section['color'])

        <div style="border-radius: 1rem; overflow: hidden; box-shadow: inset 0 0 0 1px {{ $tint($color, 28) }}">
            <div class="tone" style="{{ $tone($color) }}; display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: {{ $tint($color, 12) }}; font-size: 0.875rem; font-weight: 700">
                <x-filament::icon :icon="$section['icon']" style="flex: none; width: 1.25rem; height: 1.25rem" />
                <span>{{ $section['title'] }}</span>
            </div>

            <div style="display: grid; gap: 0.125rem; padding: 0.375rem">
                @foreach ($section['steps'] as $step)
                    <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.625rem; border-radius: 0.625rem; {{ $loop->last ? 'background: '.$tint($color, 9) : '' }}">
                        <span @class(['tone' => ! $loop->last]) style="{{ $tone($color) }}; flex: none; display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; {{ $loop->last ? 'background: var(--'.$color.'-500); color: white' : 'background: '.$tint($color, 16) }}">{{ $loop->iteration }}</span>

                        <div style="flex: 1; min-width: 0">
                            <div style="font-size: 0.875rem; font-weight: 600">{{ $step['label'] }}</div>
                            @if ($step['math'] !== null)
                                <div style="margin-top: 0.125rem; font-size: 0.75rem; opacity: 0.6">{{ $step['math'] }}</div>
                            @endif
                        </div>

                        <span @class(['tone' => $loop->last]) style="{{ $tone($color) }}; flex: none; white-space: nowrap; font-weight: 700; font-size: {{ $loop->last ? '1rem' : '0.875rem' }}">{{ $step['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @unless ($hasTransport)
        <div style="font-size: 0.8125rem; opacity: 0.6">{{ __('filament/resources/purchase_invoice.final_cost_modal.no_transport') }}</div>
    @endunless

    {{-- The two added: the final cost of one dozen. --}}
    <div style="border-radius: 1rem; padding: 1rem 1.125rem; background: {{ $tint('success', 12) }}; box-shadow: inset 0 0 0 1.5px {{ $tint('success', 40) }}">
        <div class="tone" style="{{ $tone('success') }}; display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 700">
            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckBadge" style="flex: none; width: 1.25rem; height: 1.25rem" />
            <span>{{ __('filament/resources/purchase_invoice.fields.final_dozen_cost') }}</span>
        </div>

        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-top: 0.75rem">
            @foreach ($parts as $part)
                @unless ($loop->first)
                    <span style="font-weight: 700; opacity: 0.45">+</span>
                @endunless
                <span class="tone" style="{{ $tone($part['color']) }}; border-radius: 9999px; padding: 0.25rem 0.75rem; background: {{ $tint($part['color'], 15) }}; font-size: 0.8125rem; white-space: nowrap">
                    <span style="opacity: 0.8">{{ $part['label'] }}</span>
                    <span style="font-weight: 700">{{ $part['value'] }}</span>
                </span>
            @endforeach

            <span style="font-weight: 700; opacity: 0.45">=</span>
            <span class="tone" style="{{ $tone('success') }}; margin-inline-start: auto; font-size: 1.5rem; font-weight: 800; white-space: nowrap">{{ $total }}</span>
        </div>
    </div>
</div>
