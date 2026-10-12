{{--
    Facts about the chosen item under a field of an invoice line, as one row of small coloured chips: each chip is an
    icon with a short text, and the full explanation shows on hover. The row wraps only when the column is too narrow.
    It uses Filament's own badge, so it follows the panel's colours, dark mode, and right-to-left layout.

    $facts: list of ['text' => string, 'tooltip' => string, 'color' => string, 'icon' => Heroicon]
--}}
<div style="display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.375rem">
    @foreach ($facts as $fact)
        <x-filament::badge
            :color="$fact['color']"
            :icon="$fact['icon']"
            :tooltip="$fact['tooltip']"
            size="sm"
            style="font-variant-numeric: tabular-nums"
        >
            {{ $fact['text'] }}
        </x-filament::badge>
    @endforeach
</div>
