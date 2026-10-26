{{--
    The panel's sale, laid out like the app's checkout. On one side the items the seller searches, a card each: its picture,
    its price of a dozen, what is left of the packet type it is on, its powers, the color mixes of its packets at that power,
    and its packet with + and −. On the other side the cart: each line with its power, its colors, its packets, its pieces in
    dozens at the price of a dozen, and its total, then the sale's total, the down payment of a postpaid sale and the notes
    (the form's own fields), and its confirm button. Every button calls the page, which keeps the state. Inline styles and Filament's own components keep it the same in light and dark mode and right to left.

    $items: the cards (CreateOrder::checkoutItems), $cart: the cart (CreateOrder::cartSummary)
--}}
@php
    $currency = \App\Metrics\Currency::IraqiDinar->symbol();
    $t = fn (string $key, array $replace = []): string => __("filament/resources/order.checkout.{$key}", $replace);
    $dot = fn (string $name): string => \App\Models\Color::swatch($name);
@endphp

<style>
    .waqt-pos { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1100px) {
        .waqt-pos { grid-template-columns: minmax(0, 1fr) 24rem; align-items: start; }
        .waqt-pos-cart { position: sticky; top: 5rem; }
    }
    .waqt-pos-items { container-type: inline-size; display: grid; grid-auto-flow: row dense; gap: 0.875rem; grid-template-columns: repeat(auto-fill, minmax(19rem, 1fr)); margin-top: 0.875rem; }
    .waqt-pos-card { display: flex; flex-direction: column; gap: 0.875rem; border-radius: 1rem; padding: 1rem; background: rgba(127, 127, 127, 0.06); box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.2); }
    .waqt-pos-options { display: grid; gap: 0.875rem; }
    .waqt-pos-foot { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: auto; }
    {{-- An item with powers or colors takes a whole row: its name, price and add button on one side, its choices on the other.
         The small cards of the other items sit side by side at one height, so no card leaves an empty space beside it. --}}
    .waqt-pos-card.is-wide { grid-column: 1 / -1; }
    @@container (min-width: 39rem) {
        .waqt-pos-card.is-wide { display: grid; grid-template-columns: minmax(16rem, 1fr) minmax(0, 1.35fr); grid-template-areas: "head options" "foot options"; column-gap: 1.25rem; align-items: start; }
        .waqt-pos-card.is-wide .waqt-pos-head { grid-area: head; }
        .waqt-pos-card.is-wide .waqt-pos-foot { grid-area: foot; align-self: end; }
        .waqt-pos-card.is-wide .waqt-pos-options { grid-area: options; padding-inline-start: 1.25rem; border-inline-start: 1px solid rgba(127, 127, 127, 0.2); }
    }
    .waqt-pos-card.is-in-cart { box-shadow: inset 0 0 0 2px var(--primary-500); }
    .waqt-pos-picture { flex: none; width: 4rem; height: 4rem; border-radius: 0.75rem; object-fit: cover; background: rgba(127, 127, 127, 0.12); }
    .waqt-pos-label { display: flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; font-weight: 600; opacity: 0.65; margin-bottom: 0.4rem; }
    .waqt-pos-powers { display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.15rem; }
    .waqt-pos-power { flex: none; min-width: 4.75rem; padding: 0.4rem 0.75rem; border-radius: 0.75rem; text-align: center; cursor: pointer; background: transparent; box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.3); }
    .waqt-pos-power.is-selected { background: var(--primary-600); color: #fff; box-shadow: none; }
    .waqt-pos-type { display: flex; align-items: center; gap: 0.625rem; width: 100%; padding: 0.6rem 0.75rem; border-radius: 0.75rem; cursor: pointer; text-align: start; background: transparent; box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.3); }
    .waqt-pos-type.is-selected { box-shadow: inset 0 0 0 2px var(--primary-500); background: color-mix(in oklab, var(--primary-500) 8%, transparent); }
    .waqt-pos-tag { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.5rem; border-radius: 0.5rem; font-size: 0.8125rem; background: rgba(127, 127, 127, 0.1); }
    .waqt-pos-dot { flex: none; width: 0.6rem; height: 0.6rem; border-radius: 9999px; box-shadow: 0 0 0 1px rgba(127, 127, 127, 0.45); }
    .waqt-pos-stepper { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.15rem; border-radius: 0.75rem; background: color-mix(in oklab, var(--primary-500) 10%, transparent); }
    .waqt-pos-count { min-width: 2.25rem; text-align: center; font-size: 1.125rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .waqt-pos-count-input { width: 3rem; text-align: center; font-size: 1rem; font-weight: 800; background: transparent; border: none; font-variant-numeric: tabular-nums; }
    .waqt-pos-muted { font-size: 0.8125rem; opacity: 0.65; }
    .waqt-pos-num { font-variant-numeric: tabular-nums; }
</style>

<div class="waqt-pos">
    {{-- The items. --}}
    <div>
        <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
            <x-filament::input type="search" wire:model.live.debounce.400ms="itemSearch" :placeholder="$t('search')" />
        </x-filament::input.wrapper>

        @if ($items === [])
            <div class="waqt-pos-muted" style="padding: 2.5rem 0; text-align: center">{{ $t('no_results') }}</div>
        @endif

        <div class="waqt-pos-items">
            @foreach ($items as $item)
                @php
                    $showsPowers = ! $item['isOutOfStock'] && $item['powers'] !== [];
                    $showsTypes = ! $item['isOutOfStock'] && $item['showsTypes'];
                @endphp
                <div class="waqt-pos-card {{ $item['inCart'] > 0 ? 'is-in-cart' : '' }} {{ $showsPowers || $showsTypes ? 'is-wide' : '' }}" wire:key="pos-item-{{ $item['id'] }}">
                    <div class="waqt-pos-head" style="display: flex; gap: 0.75rem; align-items: center">
                        @if ($item['image'])
                            <img class="waqt-pos-picture" src="{{ $item['image'] }}" alt="">
                        @else
                            <div class="waqt-pos-picture" style="display: flex; align-items: center; justify-content: center">
                                <x-filament::icon icon="heroicon-o-photo" style="width: 1.5rem; height: 1.5rem; opacity: 0.5" />
                            </div>
                        @endif

                        <div style="min-width: 0; flex: 1">
                            <div style="font-weight: 700; {{ $item['isOutOfStock'] ? 'opacity: 0.55' : '' }}">{{ $item['name'] }}</div>
                            <div style="margin-top: 0.15rem">
                                <span class="waqt-pos-num" style="font-size: 1.125rem; font-weight: 800; color: var(--primary-600)">{{ $item['price'] }} {{ $currency }}</span>
                                <span class="waqt-pos-muted">{{ $t('per_dozen') }}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.4rem; margin-top: 0.2rem">
                                @if ($item['isOutOfStock'])
                                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: var(--danger-500)"></span>
                                    <span style="font-size: 0.8125rem; font-weight: 600; color: var(--danger-600)">{{ $t('out_of_stock') }}</span>
                                @else
                                    <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: {{ $item['packetsLeft'] <= 2 ? 'var(--warning-500)' : 'var(--success-500)' }}"></span>
                                    <span class="waqt-pos-num" style="font-size: 0.875rem">{{ $t('available', ['dozens' => $item['dozensLeft'], 'packets' => $item['packetsLeft']]) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($showsPowers || $showsTypes)
                        <div class="waqt-pos-options">
                            @if ($showsPowers)
                                <div>
                                    <div class="waqt-pos-label"><x-filament::icon icon="heroicon-o-eye" style="width: 0.9rem; height: 0.9rem" />{{ $t('power') }}</div>
                                    <div class="waqt-pos-powers">
                                        @foreach ($item['powers'] as $power)
                                            <button type="button" class="waqt-pos-power {{ $power['isSelected'] ? 'is-selected' : '' }}"
                                                wire:click="choosePower({{ $item['id'] }}, {{ $power['id'] ?? 'null' }})">
                                                <div class="waqt-pos-num" style="font-weight: 800">{{ $power['value'] }}</div>
                                                <div class="waqt-pos-num" style="font-size: 0.6875rem; opacity: 0.8">{{ $t('dozens', ['value' => $power['dozens']]) }}</div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($showsTypes)
                                <div>
                                    <div class="waqt-pos-label"><x-filament::icon icon="heroicon-o-swatch" style="width: 0.9rem; height: 0.9rem" />{{ $t('packet_colors') }}</div>
                                    <div style="display: grid; gap: 0.5rem">
                                        @foreach ($item['types'] as $type)
                                            <button type="button" class="waqt-pos-type {{ $type['isSelected'] ? 'is-selected' : '' }}"
                                                wire:click="chooseType({{ $item['id'] }}, '{{ $type['key'] }}')">
                                                <div style="flex: 1; display: flex; flex-wrap: wrap; gap: 0.35rem">
                                                    @forelse ($type['colors'] as $color)
                                                        <span class="waqt-pos-tag">
                                                            <span class="waqt-pos-dot" style="background: {{ $dot($color['name']) }}"></span>
                                                            {{ $color['name'] }} <strong class="waqt-pos-num">{{ $color['pieces'] }}</strong>
                                                        </span>
                                                    @empty
                                                        <span class="waqt-pos-muted">{{ $t('no_colors') }}</span>
                                                    @endforelse
                                                </div>
                                                <div style="text-align: end">
                                                    <div class="waqt-pos-num" style="font-weight: 800; font-size: 0.875rem">{{ $t('dozens', ['value' => $type['dozens']]) }}</div>
                                                    <div class="waqt-pos-num waqt-pos-muted" style="font-size: 0.6875rem">{{ $t('packets', ['value' => $type['packets']]) }}</div>
                                                </div>
                                                <x-filament::icon :icon="$type['isSelected'] ? 'heroicon-s-check-circle' : 'heroicon-o-stop-circle'"
                                                    style="width: 1.25rem; height: 1.25rem; {{ $type['isSelected'] ? 'color: var(--primary-500)' : 'opacity: 0.35' }}" />
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="waqt-pos-foot">
                        <div>
                            <div style="font-size: 0.875rem; font-weight: 700">{{ $t('packet_size', ['pieces' => $item['piecesPerPacket']]) }}</div>
                            @if ($item['inCartBreakdown'])
                                <div class="waqt-pos-num" style="font-size: 0.75rem; font-weight: 600; color: var(--primary-600)">{{ $item['inCartBreakdown'] }}</div>
                            @endif
                        </div>

                        @if (! $item['isOutOfStock'])
                            @if ($item['inCart'] > 0)
                                <div class="waqt-pos-stepper">
                                    <x-filament::icon-button :icon="$item['inCart'] <= 1 ? 'heroicon-o-trash' : 'heroicon-m-minus'" :color="$item['inCart'] <= 1 ? 'danger' : 'primary'"
                                        wire:click="decrementLine('{{ $item['lineKey'] }}')" :label="$t('remove')" />
                                    <span class="waqt-pos-count">{{ $item['inCart'] }}</span>
                                    <x-filament::icon-button icon="heroicon-m-plus" color="primary" wire:click="addPacket({{ $item['id'] }})" :label="$t('add')" />
                                </div>
                            @else
                                <x-filament::button icon="heroicon-m-plus" size="sm" wire:click="addPacket({{ $item['id'] }})">
                                    {{ $t('add') }}
                                </x-filament::button>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- The cart. --}}
    <div class="waqt-pos-cart">
        <div class="waqt-pos-card" style="gap: 0.75rem">
            <div style="display: flex; align-items: center; justify-content: space-between">
                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; font-size: 1.0625rem">
                    <x-filament::icon icon="heroicon-o-shopping-cart" style="width: 1.25rem; height: 1.25rem" />
                    {{ $t('cart') }}
                    @if ($cart['lines'] !== [])
                        <x-filament::badge>{{ count($cart['lines']) }}</x-filament::badge>
                    @endif
                </div>
                @if ($cart['lines'] !== [])
                    <x-filament::link tag="button" color="danger" size="sm" wire:click="clearCart">{{ $t('clear') }}</x-filament::link>
                @endif
            </div>

            @forelse ($cart['lines'] as $line)
                <div wire:key="pos-line-{{ $line['key'] }}" style="display: grid; gap: 0.5rem; padding: 0.75rem; border-radius: 0.75rem; background: rgba(127, 127, 127, 0.07)">
                    <div style="display: flex; gap: 0.625rem; align-items: flex-start">
                        @if ($line['image'])
                            <img src="{{ $line['image'] }}" alt="" style="flex: none; width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; object-fit: cover">
                        @endif
                        <div style="flex: 1; min-width: 0">
                            <div style="font-weight: 700">{{ $line['name'] }}</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.25rem">
                                @if ($line['power'])
                                    <x-filament::badge size="sm">{{ $t('power_value', ['value' => $line['power']]) }}</x-filament::badge>
                                @endif
                                @foreach ($line['colors'] as $color)
                                    <span class="waqt-pos-tag" style="font-size: 0.75rem">
                                        <span class="waqt-pos-dot" style="background: {{ $dot($color['name']) }}"></span>
                                        {{ $color['name'] }} <strong class="waqt-pos-num">{{ $color['pieces'] }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <x-filament::icon-button icon="heroicon-o-trash" color="danger" size="sm" wire:click="removeLine('{{ $line['key'] }}')" :label="$t('remove')" />
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem">
                        <div class="waqt-pos-stepper">
                            <x-filament::icon-button icon="heroicon-m-minus" color="primary" size="sm" wire:click="decrementLine('{{ $line['key'] }}')" :label="$t('remove')" />
                            <input type="number" min="0" class="waqt-pos-count-input" value="{{ $line['packets'] }}"
                                wire:change="setPackets('{{ $line['key'] }}', $event.target.value)" aria-label="{{ $t('packets_label') }}">
                            <x-filament::icon-button icon="heroicon-m-plus" color="primary" size="sm" wire:click="incrementLine('{{ $line['key'] }}')" :label="$t('add')" />
                        </div>
                        <div style="text-align: end">
                            <div class="waqt-pos-num" style="font-weight: 800; font-size: 1.0625rem">{{ $line['totalText'] }} {{ $currency }}</div>
                            <div class="waqt-pos-num waqt-pos-muted" style="font-size: 0.75rem">{{ $line['breakdown'] }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div style="padding: 1.5rem 0; text-align: center">
                    <x-filament::icon icon="heroicon-o-shopping-cart" style="width: 2rem; height: 2rem; margin: 0 auto; opacity: 0.35" />
                    <div style="font-weight: 700; margin-top: 0.5rem">{{ $t('cart_empty') }}</div>
                    <div class="waqt-pos-muted">{{ $t('cart_empty_hint') }}</div>
                </div>
            @endforelse

            <div style="display: flex; align-items: baseline; justify-content: space-between; padding-top: 0.75rem; border-top: 1px solid rgba(127, 127, 127, 0.2)">
                <span class="waqt-pos-muted" style="font-size: 0.9375rem">{{ $t('total') }}</span>
                <span class="waqt-pos-num" style="font-size: 1.375rem; font-weight: 800">{{ $cart['totalText'] }} {{ $currency }}</span>
            </div>
            <div class="waqt-pos-muted" style="font-size: 0.75rem">{{ $t('price_by_system') }}</div>

            {{-- The down payment of a postpaid sale and the notes, as fields of the form. --}}
            {{ $getChildSchema() }}

            <x-filament::button size="lg" icon="heroicon-o-check" wire:click="create" :disabled="$cart['lines'] === []" style="width: 100%">
                {{ $t('confirm') }}
            </x-filament::button>
        </div>
    </div>
</div>
