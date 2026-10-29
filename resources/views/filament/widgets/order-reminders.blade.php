{{--
    The orders of one customer in the weekly payment reminders on the home dashboard, one line each: the order (opening it), its
    weekly payment, what is late and since how many days (or that it is not late), the next weekly payment with its amount and
    date (or that every payment already fell due), what is left with how many weekly payments, and a button that pays this order
    (the customer's payment window, opened on this order only).

    $getState(): the reminders of the customer's orders (WeeklyPaymentReminders::ordersOn)
--}}
@php
    $t = fn (string $key, array $replace = []): string => __("filament/pages/customer_payments.order_reminders.{$key}", $replace);
    $number = fn (float $value): string => \Illuminate\Support\Number::format($value, maxPrecision: 2);
@endphp

<div style="display: grid; gap: 0.4rem; padding-block: 0.5rem 0.25rem; width: 100%; min-width: 0">
    @foreach ($getState() ?? [] as $reminder)
        @php
            $order = $reminder['order'];
            $isLate = $reminder['late'] > 0.0001;
            $next = $reminder['next_due_date'];
        @endphp
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.75rem; padding: 0.4rem 0.6rem; border-radius: 0.6rem; font-size: 0.8125rem; background: rgba(127, 127, 127, 0.07); {{ $isLate ? 'box-shadow: inset 3px 0 0 var(--danger-500)' : '' }}">
            <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}"
                style="font-weight: 700; color: var(--primary-600)">{{ $t('order', ['number' => $order->id]) }}</a>

            <span style="opacity: 0.75">{{ $t('weekly', ['amount' => $number((float) $order->weekly_payment)]) }}</span>

            @if ($isLate)
                <x-filament::badge color="danger" size="sm">
                    {{ $t('late', ['amount' => $number($reminder['late']), 'days' => (int) $reminder['late_since']->diffInDays(today())]) }}
                </x-filament::badge>
            @else
                <x-filament::badge color="success" size="sm">{{ $t('not_late') }}</x-filament::badge>
            @endif

            @if ($next !== null)
                <x-filament::badge :color="$next->isToday() ? 'warning' : 'gray'" size="sm">
                    {{ $next->isToday()
                        ? $t('next_today', ['amount' => $number($reminder['next_amount'])])
                        : $t('next', ['amount' => $number($reminder['next_amount']), 'date' => $next->format('Y/m/d')]) }}
                </x-filament::badge>
            @else
                <span style="opacity: 0.65">{{ $t('no_next') }}</span>
            @endif

            <span style="margin-inline-start: auto; font-weight: 600">
                {{ $t('left', ['amount' => $number($reminder['remaining']), 'weeks' => $reminder['weeks_left']]) }}
            </span>

            @can('create', \App\Models\CustomerPayment::class)
                <x-filament::link tag="button" size="sm" color="success" icon="heroicon-o-banknotes"
                    wire:click="mountAction('payDebt', {{ \Illuminate\Support\Js::from(['order' => $order->id]) }}, {{ \Illuminate\Support\Js::from(['recordKey' => (string) $order->customer_id, 'table' => true]) }})">
                    {{ __('filament/resources/customer.actions.pay_debt.label') }}
                </x-filament::link>
            @endcan
        </div>
    @endforeach
</div>
