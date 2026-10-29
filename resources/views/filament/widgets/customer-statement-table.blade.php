{{--
    The account statement tab: the customer and period form on top, the summary cards of the period under it, then the statement,
    by order (a tree: every postpaid order with what it came to, what was paid and what is left, its weekly payment with what is late
    and the next one, and under it each payment that went to it with what was left after it) or by date (the statement table, one line per movement).
    The cards reuse the colored totals of the reports. Everything follows the panel's dark mode and right-to-left layout.

    $summary: the summary cards, $hasCustomer: whether a customer is chosen, $orderTree: the orders with their payments (CustomerStatementTable::orderTree)
--}}
@php
    $t = fn (string $key, array $replace = []): string => __("filament/resources/customer.statement.{$key}", $replace);
    $money = fn (float $value): string => \Illuminate\Support\Number::format($value, maxPrecision: 2);
    $currency = \App\Metrics\Currency::IraqiDinar->symbol();
@endphp


<x-filament-widgets::widget class="fi-wi-table">
    <style>
        .waqt-tree-switch { display: inline-flex; padding: 0.2rem; gap: 0.2rem; border-radius: 0.75rem; background: rgba(127, 127, 127, 0.12); }
        .waqt-tree-switch button { padding: 0.4rem 0.9rem; border-radius: 0.55rem; font-size: 0.875rem; font-weight: 600; opacity: 0.7; cursor: pointer; }
        .waqt-tree-switch button.is-on { background: var(--primary-600); color: #fff; opacity: 1; }
        .waqt-tree-order { border-radius: 1rem; padding: 1rem 1.1rem; background: rgba(127, 127, 127, 0.06); box-shadow: inset 0 0 0 1px rgba(127, 127, 127, 0.2); }
        .waqt-tree-figures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; margin-top: 0.75rem; }
        .waqt-tree-figure { border-radius: 0.75rem; padding: 0.5rem 0.75rem; }
        .waqt-tree-bar { height: 0.4rem; border-radius: 9999px; margin-top: 0.75rem; background: rgba(127, 127, 127, 0.18); overflow: hidden; }
        {{-- The payments hang from the order on one line, each on its own branch. --}}
        .waqt-tree-branches { position: relative; margin-top: 0.9rem; margin-inline-start: 0.6rem; padding-inline-start: 1.25rem; border-inline-start: 2px solid rgba(127, 127, 127, 0.3); display: grid; gap: 0.6rem; }
        .waqt-tree-branch { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.9rem; padding: 0.55rem 0.75rem; border-radius: 0.65rem; background: rgba(127, 127, 127, 0.07); font-size: 0.875rem; }
        .waqt-tree-branch::before { content: ''; position: absolute; inset-inline-start: calc(-1.25rem - 1px); top: 50%; width: 1.1rem; border-top: 2px solid rgba(127, 127, 127, 0.3); }
        .waqt-tree-branch::after { content: ''; position: absolute; inset-inline-start: calc(-1.25rem - 0.4rem); top: calc(50% - 0.3rem); width: 0.6rem; height: 0.6rem; border-radius: 9999px; background: var(--success-500); }
        .waqt-tree-branch.is-down-payment::after { background: var(--info-500); }
        .waqt-tree-muted { opacity: 0.65; font-size: 0.8125rem; }
        .waqt-num { font-variant-numeric: tabular-nums; }
    </style>

    <div style="display: flex; flex-direction: column; gap: 1.5rem">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        @if ($summary !== [])
            @include('filament.reports.totals', ['totals' => $summary])
        @endif

        @if ($hasCustomer)
            <div class="waqt-tree-switch" role="group">
                <button type="button" @class(['is-on' => $this->layout === 'orders']) wire:click="$set('layout', 'orders')">{{ $t('layouts.orders') }}</button>
                <button type="button" @class(['is-on' => $this->layout === 'dates']) wire:click="$set('layout', 'dates')">{{ $t('layouts.dates') }}</button>
            </div>
        @endif

        @if ($hasCustomer && $this->layout === 'orders')
            <div style="display: grid; gap: 1rem">
                @forelse ($orderTree as $order)
                    @php $isSettled = $order['remaining'] <= 0.0001; @endphp
                    <div class="waqt-tree-order" wire:key="statement-order-{{ $order['id'] }}">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem">
                            <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; font-size: 1rem">
                                <x-filament::icon icon="heroicon-o-shopping-bag" style="width: 1.25rem; height: 1.25rem; color: var(--warning-500)" />
                                <span class="waqt-num">{{ $t('tree.order', ['id' => $order['id'], 'date' => $order['date'], 'items' => $order['items']]) }}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem">
                                <x-filament::badge :color="$isSettled ? 'success' : 'danger'">
                                    {{ $isSettled ? $t('tree.settled') : $t('tree.open', ['amount' => $money($order['remaining']).' '.$currency]) }}
                                </x-filament::badge>
                                <x-filament::link :href="$order['url']" icon="heroicon-o-arrow-top-right-on-square" size="sm">{{ $t('tree.open_order') }}</x-filament::link>
                            </div>
                        </div>

                        @if ($order['weekly'] !== null && ! $isSettled)
                            @php $remind = fn (string $key, array $replace = []): string => __("filament/pages/customer_payments.order_reminders.{$key}", $replace); @endphp
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; margin-top: 0.6rem">
                                <x-filament::badge color="gray" icon="heroicon-o-calendar-days">{{ $remind('weekly', ['amount' => $money($order['weekly'])]) }}</x-filament::badge>
                                @if ($order['late'] > 0.0001)
                                    <x-filament::badge color="danger">{{ $remind('late', ['amount' => $money($order['late']), 'days' => $order['late_days']]) }}</x-filament::badge>
                                @else
                                    <x-filament::badge color="success">{{ $remind('not_late') }}</x-filament::badge>
                                @endif
                                @if ($order['next_date'] !== null)
                                    <x-filament::badge :color="$order['next_date']->isToday() ? 'warning' : 'gray'">
                                        {{ $order['next_date']->isToday()
                                            ? $remind('next_today', ['amount' => $money($order['next_amount'])])
                                            : $remind('next', ['amount' => $money($order['next_amount']), 'date' => $order['next_date']->format('Y/m/d')]) }}
                                    </x-filament::badge>
                                @endif
                            </div>
                        @endif

                        <div class="waqt-tree-figures">
                            <div class="waqt-tree-figure" style="background: color-mix(in oklab, var(--warning-500) 12%, transparent)">
                                <div class="waqt-tree-muted">{{ $t('tree.total') }}</div>
                                <div class="waqt-num" style="font-weight: 800">{{ $money($order['total']) }} <small>{{ $currency }}</small></div>
                            </div>
                            <div class="waqt-tree-figure" style="background: color-mix(in oklab, var(--success-500) 12%, transparent); color: var(--success-600)">
                                <div class="waqt-tree-muted">{{ $t('tree.paid') }}</div>
                                <div class="waqt-num" style="font-weight: 800">{{ $money($order['paid']) }} <small>{{ $currency }}</small></div>
                            </div>
                            <div class="waqt-tree-figure" style="background: color-mix(in oklab, var(--{{ $isSettled ? 'success' : 'danger' }}-500) 12%, transparent); color: var(--{{ $isSettled ? 'success' : 'danger' }}-600)">
                                <div class="waqt-tree-muted">{{ $t('tree.remaining') }}</div>
                                <div class="waqt-num" style="font-weight: 800">{{ $money($order['remaining']) }} <small>{{ $currency }}</small></div>
                            </div>
                        </div>

                        <div class="waqt-tree-bar" title="{{ $order['percent'] }}%">
                            <div style="height: 100%; width: {{ $order['percent'] }}%; background: var(--success-500)"></div>
                        </div>

                        @if ($order['payments'] === [])
                            <div class="waqt-tree-muted" style="margin-top: 0.75rem">{{ $t('tree.no_payments') }}</div>
                        @else
                            <div class="waqt-tree-branches">
                                @foreach ($order['payments'] as $payment)
                                    <div @class(['waqt-tree-branch', 'is-down-payment' => $payment['is_down_payment']])>
                                        <span class="waqt-num" style="font-weight: 700">{{ $payment['date'] }}</span>
                                        <x-filament::badge :color="$payment['is_down_payment'] ? 'info' : 'success'" size="sm">
                                            {{ $payment['is_down_payment'] ? $t('types.down_payment') : $t('types.payment') }}
                                        </x-filament::badge>
                                        <span class="waqt-num" style="font-weight: 800; color: var(--success-600)">− {{ $money($payment['amount']) }} {{ $currency }}</span>
                                        <span class="waqt-num" style="font-weight: 600">{{ $t('tree.left_after', ['amount' => $money($payment['left_after']).' '.$currency]) }}</span>
                                        @if (filled($payment['user']))
                                            <span class="waqt-tree-muted">{{ $t('tree.by', ['user' => $payment['user']]) }}</span>
                                        @endif
                                        @if (filled($payment['notes']))
                                            <span class="waqt-tree-muted">{{ $payment['notes'] }}</span>
                                        @endif
                                        <x-filament::link :href="$payment['receipt']" target="_blank" icon="heroicon-o-printer" size="sm" color="gray" style="margin-inline-start: auto">
                                            {{ $t('tree.receipt') }}
                                        </x-filament::link>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="waqt-tree-muted" style="padding: 2rem 0; text-align: center">{{ $t('tree.empty') }}</div>
                @endforelse
            </div>
        @else
            {{ $this->table }}
        @endif
    </div>
</x-filament-widgets::widget>
