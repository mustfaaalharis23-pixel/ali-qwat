{{--
    The colors and the powers of a purchase line, opened from its badges on the invoice page. First the colors of one packet,
    chosen once for all the line's packets: a bar of how the packet is shared between them, and a row per color with its
    pieces in one packet and on the whole line. Then a row per power with its packets, pieces, and dozens.
    Colors known by name get their swatch; any other color gets a neutral dot. It uses only inline styles and Filament's own
    badge, so it reads the same in light and dark mode and right to left.

    $colors: list of ['name' => string, 'perPacket' => string, 'pieces' => string, 'share' => float]
    $groups: list of ['power' => string, 'hasPower' => bool, 'packets' => string, 'pieces' => string, 'dozens' => string]
--}}
@php
    $swatch = fn (string $name): string => \App\Models\Color::swatch($name);
    $card = 'border-radius: 0.875rem; padding: 1rem 1.125rem; background: rgba(127, 127, 127, 0.07); box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.18)';
    $title = 'font-size: 0.8125rem; font-weight: 600; opacity: 0.65; margin-bottom: 0.75rem';
@endphp

<div style="display: grid; gap: 0.875rem">
    <div style="{{ $card }}">
        <div style="{{ $title }}">{{ __('filament/resources/invoice_item.fields.colors') }}</div>

        @if ($colors === [])
            <div style="font-size: 0.875rem; opacity: 0.6">{{ __('filament/resources/purchase_invoice.no_colors') }}</div>
        @else
            {{-- How one packet is shared between the colors. --}}
            <div style="display: flex; height: 0.625rem; border-radius: 9999px; overflow: hidden; box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.25)">
                @foreach ($colors as $color)
                    <div style="width: {{ $color['share'] * 100 }}%; background: {{ $swatch($color['name']) }}"></div>
                @endforeach
            </div>

            <div style="display: grid; gap: 0.5rem; margin-top: 0.875rem">
                @foreach ($colors as $color)
                    <div style="display: flex; align-items: center; gap: 0.625rem; font-variant-numeric: tabular-nums">
                        <span style="flex: none; width: 0.875rem; height: 0.875rem; border-radius: 9999px; background: {{ $swatch($color['name']) }}; box-shadow: 0 0 0 1px rgba(127, 127, 127, 0.4)"></span>
                        <span style="flex: 1; font-weight: 600">{{ $color['name'] }}</span>
                        <span style="font-size: 0.875rem">{{ $color['perPacket'] }}</span>
                        <span style="min-width: 6.5rem; text-align: end; font-size: 0.8125rem; opacity: 0.6">{{ $color['pieces'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($groups !== [])
        <div style="{{ $card }}">
            <div style="{{ $title }}">{{ __('filament/resources/invoice_item.fields.groups') }}</div>

            <div style="display: grid; gap: 0.625rem">
                @foreach ($groups as $group)
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem">
                        <x-filament::badge :color="$group['hasPower'] ? 'primary' : 'gray'" size="lg">
                            {{ $group['power'] }}
                        </x-filament::badge>

                        <div style="display: flex; flex-wrap: wrap; gap: 0.375rem; font-variant-numeric: tabular-nums">
                            <x-filament::badge color="info">{{ $group['packets'] }}</x-filament::badge>
                            <x-filament::badge color="gray">{{ $group['pieces'] }}</x-filament::badge>
                            <x-filament::badge color="success">{{ $group['dozens'] }}</x-filament::badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
