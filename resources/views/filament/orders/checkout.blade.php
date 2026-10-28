{{--
    The panel's sale, laid out like the app's checkout. On one side the items the seller searches, a card each: its picture,
    its price of a dozen, what is left of the packet type it is on, its powers (a grid that wraps, so many powers never push
    the card past the screen; past a dozen of them the rest open with "show all"), the color mixes of its packets at that
    power, and a field the seller types its packets in. On the other side the cart: each line with its power, its colors,
    its packets (typed too), its pieces in dozens at the price of a dozen, and its total, then the sale's total before its discount, one discount off the whole sale (an amount or a percent; a
    percent shows its amount), and what it comes to, the down
    payment of a postpaid sale and the notes (the form's own fields), and its confirm button. Every button calls the page,
    which keeps the state. Inline styles and Filament's own components keep it the same in light and dark mode and right to left.

    $items: the cards (InteractsWithCheckoutCart::checkoutItems), $cart: the cart (InteractsWithCheckoutCart::cartSummary),
    $submit: the page's method the confirm button calls (create on a new sale, save on an edit) and its label
--}}
@php
    $currency = \App\Metrics\Currency::IraqiDinar->symbol();
    $t = fn (string $key, array $replace = []): string => __("filament/resources/order.checkout.{$key}", $replace);
    $dot = fn (string $name): string => \App\Models\Color::swatch($name);
    // The powers a card shows before "show all".
    $powersShown = 12;
@endphp

<style>
    .waqt-pos { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1100px) {
        .waqt-pos { grid-template-columns: minmax(0, 1fr) 24rem; align-items: start; }
        .waqt-pos-cart { position: sticky; top: 5rem; }
    }
    .waqt-pos-items { container-type: inline-size; display: grid; grid-auto-flow: row dense; gap: 0.875rem; grid-template-columns: repeat(auto-fill, minmax(min(19rem, 100%), 1fr)); margin-top: 0.875rem; }
    .waqt-pos-card { min-width: 0; display: flex; flex-direction: column; gap: 0.875rem; border-radius: 1rem; padding: 1rem; background: rgba(127, 127, 127, 0.06); box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.2); }
    .waqt-pos-options { min-width: 0; display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
    .waqt-pos-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.625rem 0.75rem; margin-top: auto; }
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
    {{-- The powers wrap into rows of small chips, so a card with many powers grows down, never sideways. --}}
    .waqt-pos-powers { display: grid; grid-template-columns: repeat(auto-fill, minmax(4.25rem, 1fr)); gap: 0.4rem; }
    .waqt-pos-powers.is-collapsed .waqt-pos-power.is-extra { display: none; }
    .waqt-pos-power { min-width: 0; padding: 0.35rem 0.25rem; border-radius: 0.625rem; text-align: center; line-height: 1.3; cursor: pointer; background: transparent; box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.3); }
    .waqt-pos-power > div { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .waqt-pos-power.is-selected { background: var(--primary-600); color: #fff; box-shadow: none; }
    .waqt-pos-power.is-toggle { color: var(--primary-600); box-shadow: none; border: 1px dashed color-mix(in oklab, var(--primary-500) 55%, transparent); background: color-mix(in oklab, var(--primary-500) 6%, transparent); }
    .waqt-pos-type { display: flex; align-items: center; gap: 0.625rem; width: 100%; padding: 0.6rem 0.75rem; border-radius: 0.75rem; cursor: pointer; text-align: start; background: transparent; box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.3); }
    .waqt-pos-type.is-selected { box-shadow: inset 0 0 0 2px var(--primary-500); background: color-mix(in oklab, var(--primary-500) 8%, transparent); }
    .waqt-pos-tag { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.5rem; border-radius: 0.5rem; font-size: 0.8125rem; background: rgba(127, 127, 127, 0.1); }
    .waqt-pos-dot { flex: none; width: 0.6rem; height: 0.6rem; border-radius: 9999px; box-shadow: 0 0 0 1px rgba(127, 127, 127, 0.45); }
    {{-- The packets are typed, not counted with + and −. --}}
    .waqt-pos-qty { display: flex; align-items: center; gap: 0.5rem; flex: 1 1 12rem; max-width: 17rem; margin-inline-start: auto; }
    .waqt-pos-qty.is-compact { flex: 0 1 9rem; margin-inline-start: 0; }
    .waqt-pos-qty.is-discount { flex: 1 1 auto; max-width: none; margin-inline-start: 0; flex-wrap: wrap; }
    .waqt-pos-qty.is-discount .waqt-pos-qty-field { flex: 1 1 8rem; }
    {{-- The switch between a discount in dinars and a discount in percent. --}}
    .waqt-pos-switch { flex: none; display: inline-flex; padding: 0.15rem; gap: 0.15rem; border-radius: 0.6rem; background: rgba(127, 127, 127, 0.12); }
    .waqt-pos-switch button { min-width: 2.25rem; padding: 0.3rem 0.5rem; border-radius: 0.45rem; font-size: 0.75rem; font-weight: 700; opacity: 0.7; cursor: pointer; }
    .waqt-pos-switch button.is-on { background: var(--primary-600); color: #fff; opacity: 1; }
    .waqt-pos-qty-field { flex: 1; min-width: 0; }
    .waqt-pos-qty input { text-align: center; font-weight: 800; font-variant-numeric: tabular-nums; -moz-appearance: textfield; appearance: textfield; }
    .waqt-pos-qty input::-webkit-outer-spin-button, .waqt-pos-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
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
                                @php
                                    // Past a dozen powers (and more than one to hide), the rest wait behind "show all"; the chosen one always shows.
                                    $collapsesPowers = count($item['powers']) > $powersShown + 1;
                                    $hiddenPowers = $collapsesPowers
                                        ? collect($item['powers'])->slice($powersShown)->reject(fn (array $power): bool => $power['isSelected'])->count()
                                        : 0;
                                @endphp
                                <div x-data="{ showsAllPowers: false }">
                                    <div class="waqt-pos-label">
                                        <x-filament::icon icon="heroicon-o-eye" style="width: 0.9rem; height: 0.9rem" />{{ $t('power') }}
                                        <span class="waqt-pos-num" style="font-weight: 500">({{ count($item['powers']) }})</span>
                                    </div>
                                    <div @class(['waqt-pos-powers', 'is-collapsed' => $collapsesPowers])
                                        @if ($collapsesPowers) x-bind:class="{ 'is-collapsed': ! showsAllPowers }" @endif>
                                        @foreach ($item['powers'] as $power)
                                            <button type="button" @class(['waqt-pos-power', 'is-selected' => $power['isSelected'], 'is-extra' => $loop->index >= $powersShown && ! $power['isSelected']])
                                                wire:click="choosePower({{ $item['id'] }}, {{ $power['id'] ?? 'null' }})">
                                                <div class="waqt-pos-num" style="font-weight: 800; font-size: 0.875rem">{{ $power['value'] }}</div>
                                                <div class="waqt-pos-num" style="font-size: 0.625rem; opacity: 0.8">{{ $t('dozens', ['value' => $power['dozens']]) }}</div>
                                            </button>
                                        @endforeach
                                        @if ($collapsesPowers)
                                            <button type="button" class="waqt-pos-power is-toggle" x-on:click="showsAllPowers = ! showsAllPowers">
                                                <div class="waqt-pos-num" dir="ltr" style="font-weight: 800; font-size: 0.875rem" x-text="showsAllPowers ? '−' : '+{{ $hiddenPowers }}'">+{{ $hiddenPowers }}</div>
                                                <div style="font-size: 0.625rem; font-weight: 600" x-text="showsAllPowers ? '{{ $t('show_less') }}' : '{{ $t('show_all') }}'">{{ $t('show_all') }}</div>
                                            </button>
                                        @endif
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
                                                <div style="flex: 1; min-width: 0; display: flex; flex-wrap: wrap; gap: 0.35rem">
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
                        <div style="min-width: 0">
                            <div style="font-size: 0.875rem; font-weight: 700">{{ $t('packet_size', ['pieces' => $item['piecesPerPacket']]) }}</div>
                            @if ($item['inCartBreakdown'])
                                <div class="waqt-pos-num" style="font-size: 0.75rem; font-weight: 600; color: var(--primary-600)">{{ $item['inCartBreakdown'] }}</div>
                            @endif
                        </div>

                        @if (! $item['isOutOfStock'])
                            @if ($item['inCart'] > 0)
                                {{-- In the cart: the field holds its packets, and a new number replaces them (Enter or leaving the field). The key
                                     brings the field back with the cart's number, also when the stock cut what was typed. --}}
                                <div class="waqt-pos-qty" wire:key="pos-qty-{{ $item['lineKey'] }}-{{ $item['inCart'] }}">
                                    <x-filament::input.wrapper :suffix="$t('packets_unit')" class="waqt-pos-qty-field">
                                        <x-filament::input type="number" inputmode="numeric" min="0" step="1" value="{{ $item['inCart'] }}" aria-label="{{ $t('packets_label') }}"
                                            x-on:keydown.enter.prevent="$el.blur()"
                                            x-on:change="$wire.setPackets('{{ $item['lineKey'] }}', $el.value).then(() => $el.value = $el.defaultValue)" />
                                    </x-filament::input.wrapper>
                                    <x-filament::icon-button icon="heroicon-o-trash" color="danger" wire:click="removeLine('{{ $item['lineKey'] }}')" :label="$t('remove')" />
                                </div>
                            @else
                                {{-- Not in the cart yet: the seller types the packets (one when left empty) and adds them. --}}
                                <div class="waqt-pos-qty" x-data="{ packets: '' }">
                                    <x-filament::input.wrapper :suffix="$t('packets_unit')" class="waqt-pos-qty-field">
                                        <x-filament::input type="number" inputmode="numeric" min="1" step="1" placeholder="1" x-model="packets" aria-label="{{ $t('packets_label') }}"
                                            x-on:keydown.enter.prevent="$wire.addPackets({{ $item['id'] }}, packets || 1)" />
                                    </x-filament::input.wrapper>
                                    <x-filament::button icon="heroicon-m-plus" x-on:click="$wire.addPackets({{ $item['id'] }}, packets || 1)">
                                        {{ $t('add') }}
                                    </x-filament::button>
                                </div>
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
                        <div class="waqt-pos-qty is-compact" wire:key="pos-line-qty-{{ $line['key'] }}-{{ $line['packets'] }}">
                            <x-filament::input.wrapper :suffix="$t('packets_unit')" class="waqt-pos-qty-field">
                                <x-filament::input type="number" inputmode="numeric" min="0" step="1" value="{{ $line['packets'] }}" aria-label="{{ $t('packets_label') }}"
                                    x-on:keydown.enter.prevent="$el.blur()"
                                    x-on:change="$wire.setPackets('{{ $line['key'] }}', $el.value).then(() => $el.value = $el.defaultValue)" />
                            </x-filament::input.wrapper>
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

            <div style="display: grid; gap: 0.35rem; padding-top: 0.75rem; border-top: 1px solid rgba(127, 127, 127, 0.2)">
                @if ($cart['discount'] > 0)
                    <div style="display: flex; align-items: baseline; justify-content: space-between">
                        <span class="waqt-pos-muted">{{ $t('subtotal') }}</span>
                        <span class="waqt-pos-num" style="font-weight: 700">{{ $cart['subtotalText'] }} {{ $currency }}</span>
                    </div>
                @endif

                {{-- One discount off the whole sale, in dinars or in percent. A percent shows what it takes off in dinars beside it.
                     Left empty, the sale has none. --}}
                @if ($cart['lines'] !== [])
                    @php
                        $isPercent = $cart['discountPercent'] !== null;
                        $discountValue = $isPercent ? $cart['discountPercent'] : $cart['discount'];
                    @endphp
                    <div class="waqt-pos-qty is-discount" wire:key="pos-discount-{{ $isPercent ? 'percent' : 'amount' }}-{{ $discountValue }}">
                        <div class="waqt-pos-switch" role="group" aria-label="{{ $t('discount') }}">
                            <button type="button" @class(['is-on' => ! $isPercent]) wire:click="setDiscountMode('amount')">{{ $currency }}</button>
                            <button type="button" @class(['is-on' => $isPercent]) wire:click="setDiscountMode('percent')">%</button>
                        </div>
                        <x-filament::input.wrapper :prefix="$t('discount')" :suffix="$isPercent ? '%' : $currency" class="waqt-pos-qty-field">
                            <x-filament::input type="number" inputmode="decimal" min="0" step="any" placeholder="0"
                                value="{{ $discountValue > 0 ? $discountValue : '' }}" aria-label="{{ $t('discount') }}"
                                x-on:keydown.enter.prevent="$el.blur()"
                                x-on:change="$wire.{{ $isPercent ? 'setDiscountPercent' : 'setDiscount' }}($el.value).then(() => $el.value = $el.defaultValue)" />
                        </x-filament::input.wrapper>
                        @if ($isPercent && $cart['discount'] > 0)
                            <span class="waqt-pos-num" style="flex: none; font-size: 0.8125rem; font-weight: 700; color: var(--danger-600)">
                                {{ $t('discount_amount', ['amount' => $cart['discountText'].' '.$currency]) }}
                            </span>
                        @endif
                    </div>
                @endif
                <div style="display: flex; align-items: baseline; justify-content: space-between">
                    <span class="waqt-pos-muted" style="font-size: 0.9375rem">{{ $cart['discount'] > 0 ? $t('net_total') : $t('total') }}</span>
                    <span class="waqt-pos-num" style="font-size: 1.375rem; font-weight: 800">{{ $cart['totalText'] }} {{ $currency }}</span>
                </div>
            </div>
            <div class="waqt-pos-muted" style="font-size: 0.75rem">{{ $t('price_by_system') }}</div>

            {{-- The down payment of a postpaid sale and the notes, as fields of the form. --}}
            {{ $getChildSchema() }}

            <x-filament::button size="lg" icon="heroicon-o-check" wire:click="{{ $submit['action'] }}" :disabled="$cart['lines'] === []" style="width: 100%">
                {{ $submit['label'] }}
            </x-filament::button>
        </div>
    </div>
</div>
