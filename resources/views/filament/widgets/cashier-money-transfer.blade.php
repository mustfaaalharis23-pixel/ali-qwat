@php
    use Filament\Support\Icons\Heroicon;
@endphp

{{--
    The cashier money transfer card: the label with its info icon on the first row,
    and the cashier list, each cashier with the money on them, with the transfer button next to it under them.
    It uses Filament's own stat card classes and components, so it follows the panel's colors, dark mode, and right-to-left layout.
--}}
<x-filament-widgets::widget class="fi-wi-value-metric">
    <div class="fi-wi-stats-overview-stat">
        <div class="fi-wi-stats-overview-stat-content" style="row-gap: 1.25rem">
            <div style="display: flex; align-items: center; gap: 0.25rem; min-width: 0">
                <span class="fi-wi-stats-overview-stat-label" style="min-width: 0; font-size: 1.125rem; line-height: 1.5rem">
                    {{ $label }}
                </span>

                <x-filament::modal
                    :id="$this->getId() . '-info'"
                    :heading="$label"
                    :icon="Heroicon::OutlinedInformationCircle"
                    teleport="body"
                    width="lg"
                >
                    <x-slot name="trigger">
                        <x-filament::icon-button
                            :icon="Heroicon::OutlinedInformationCircle"
                            :label="__('metrics.info.open')"
                            color="gray"
                            size="sm"
                        />
                    </x-slot>

                    <div style="display: grid; row-gap: 1.25rem">
                        <div style="display: grid; row-gap: 0.375rem">
                            <h3 style="font-weight: 600">
                                {{ __('metrics.info.explanation') }}
                            </h3>

                            <p style="line-height: 1.75rem">
                                {{ $explanation }}
                            </p>
                        </div>

                        <div style="display: grid; row-gap: 0.375rem; border-inline-start: 3px solid var(--primary-500); padding-inline-start: 0.75rem">
                            <h3 style="font-weight: 600">
                                {{ __('metrics.info.example') }}
                            </h3>

                            <p style="line-height: 1.75rem">
                                {{ $example }}
                            </p>
                        </div>
                    </div>
                </x-filament::modal>
            </div>

            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem">
                <x-filament::input.wrapper
                    wire:target="cashier"
                    style="flex: 1 1 14rem; min-width: 0"
                >
                    <x-filament::input.select
                        :aria-label="__('metrics.cashier_money_transfer.cashier')"
                        wire:model.live="cashier"
                    >
                        <option value="">
                            {{ __('metrics.cashier_money_transfer.choose_cashier') }}
                        </option>

                        @foreach ($cashiers as $cashier)
                            <option value="{{ $cashier['id'] }}">
                                {{ $cashier['label'] }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                @if ($this->transferAction->isVisible())
                    <div style="flex-shrink: 0">
                        {{ $this->transferAction }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
