{{--
    The shared look of every value metric card:
    the label with its info icon and the range list on the first row, and the number under them.
    The info icon opens a modal with the card's explanation and a simple example; every card has one.
    It uses Filament's own stat card classes and components, so it follows the panel's colors, dark mode, and right-to-left layout.
--}}
<x-filament-widgets::widget class="fi-wi-value-metric">
    <div class="fi-wi-stats-overview-stat">
        <div class="fi-wi-stats-overview-stat-content" style="row-gap: 1.25rem">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem">
                <div style="display: flex; align-items: center; gap: 0.25rem; min-width: 0">
                    <span class="fi-wi-stats-overview-stat-label" style="min-width: 0; font-size: 1.125rem; line-height: 1.5rem">
                        {{ $label }}
                    </span>

                    <x-info-modal
                        :id="$this->getId() . '-info'"
                        :heading="$label"
                        :explanation="$explanation"
                        :note-title="__('metrics.info.example')"
                        :note="$example"
                    />
                </div>

                @if (count($ranges) > 1)
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="range"
                        style="width: max-content; flex-shrink: 0"
                    >
                        <x-filament::input.select
                            :aria-label="__('metrics.range')"
                            inline-prefix
                            wire:model.live="range"
                            style="font-size: 0.8125rem; line-height: 1.25rem; padding-block: 0.25rem"
                        >
                            @foreach ($ranges as $range)
                                <option value="{{ $range->value }}">
                                    {{ $range->getLabel() }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif
            </div>

            <div style="display: flex; flex-wrap: wrap; align-items: baseline; column-gap: 0.5rem">
                <span class="fi-wi-stats-overview-stat-value" style="font-size: 1.875rem; line-height: 1">
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
</x-filament-widgets::widget>
