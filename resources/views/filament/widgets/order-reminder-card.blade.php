{{--
    One customer in the weekly payment reminders on the home dashboard, as a card that fits the screen. One straight line on top:
    the customer (opening them) with their phone (calling it) on one side, and on the other, at the same height, what is due today
    (on the today tab), what is late and for how many days (or that nothing is late), what is left, and the buttons that pay the
    customer and open their statement. On a phone the line wraps. Under it, the customer's orders, one line each.

    $getRecord(): the customer, $getState(): the card (OrderRemindersTable)
--}}
@php
    $customer = $getRecord();
    $card = $getState();
    $t = fn (string $key, array $replace = []): string => __("filament/pages/customer_payments.order_reminders.{$key}", $replace);
    $number = fn (float $value): string => \Illuminate\Support\Number::format($value, maxPrecision: 2);
    $canPay = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\CustomerPayment::class);
@endphp

<div style="display: grid; gap: 0.75rem; width: 100%; min-width: 0; padding-block: 0.25rem">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.6rem 1rem">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 1rem; min-width: 0">
            <a href="{{ \App\Filament\Resources\Customers\CustomerResource::getUrl('view', ['record' => $customer]) }}"
                style="display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 800; font-size: 1rem">
                <x-filament::icon icon="heroicon-o-user-circle" style="width: 1.25rem; height: 1.25rem; opacity: 0.6" />
                {{ $customer->name }}
            </a>
            @if (filled($customer->phone))
                <a href="tel:{{ $customer->phone }}" style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.875rem; opacity: 0.7; font-variant-numeric: tabular-nums">
                    <x-filament::icon icon="heroicon-o-phone" style="width: 1rem; height: 1rem" />
                    {{ $customer->phone }}
                </a>
            @endif
        </div>

        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem">
            @if ($card['shows_due_today'] && $card['due_today'] > 0)
                <x-filament::badge color="warning" icon="heroicon-o-bell-alert">{{ $t('due_today_value', ['amount' => $number($card['due_today'])]) }}</x-filament::badge>
            @endif

            @if ($card['late'] > 0.0001)
                <x-filament::badge color="danger" icon="heroicon-o-exclamation-triangle">
                    {{ $t('late', ['amount' => $number($card['late']), 'days' => $card['days_late']]) }}
                </x-filament::badge>
            @else
                <x-filament::badge color="success" icon="heroicon-o-check-circle">{{ $t('not_late') }}</x-filament::badge>
            @endif

            <x-filament::badge color="gray">{{ $t('remaining_value', ['amount' => $number($card['remaining'])]) }}</x-filament::badge>

            @if ($canPay)
                <x-filament::button size="sm" color="success" icon="heroicon-o-banknotes"
                    wire:click="mountAction('payDebt', {}, {{ \Illuminate\Support\Js::from(['recordKey' => (string) $customer->getKey(), 'table' => true]) }})">
                    {{ __('filament/resources/customer.actions.pay_debt.label') }}
                </x-filament::button>
            @endif
            <x-filament::button size="sm" color="gray" icon="heroicon-o-document-text" tag="a" :href="$card['statement_url']">
                {{ __('filament/resources/customer.actions.statement') }}
            </x-filament::button>
        </div>
    </div>

    @include('filament.widgets.order-reminders', ['getState' => fn (): array => $card['orders']])
</div>
