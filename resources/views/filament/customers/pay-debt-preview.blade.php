{{--
    The preview in the pay debt form: three boxes (the current debt, the amount being paid, and what remains after it),
    then every order the customer still owes on, oldest first, with what the typed amount pays on it: fully, partly, or nothing yet.
    It uses Filament's color variables, so it follows the panel's dark mode and right-to-left layout.
--}}
@php
    $box = 'border-radius: 0.75rem; padding: 0.75rem 1rem; display: flex; flex-direction: column; gap: 0.25rem';
    $boxLabel = 'font-size: 0.8125rem; opacity: 0.8';
    $boxValue = 'font-size: 1.25rem; font-weight: 700; line-height: 1.75rem';
    $statusColors = ['full' => 'success', 'partial' => 'warning', 'untouched' => 'gray'];
@endphp

@if (! $hasCustomer)
    <div style="border: 1px dashed var(--gray-400); border-radius: 0.75rem; padding: 1rem; text-align: center; opacity: 0.8">
        {{ __('filament/resources/customer.actions.pay_debt.preview.choose_customer') }}
    </div>
@else
    <div style="display: flex; flex-direction: column; gap: 1rem">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.75rem">
            <div style="{{ $box }}; background: color-mix(in oklab, var(--danger-500) 12%, transparent); color: var(--danger-600)">
                <span style="{{ $boxLabel }}">{{ __('filament/resources/customer.actions.pay_debt.preview.current_debt') }}</span>
                <span style="{{ $boxValue }}">{{ $debt->number }} <small>{{ $debt->currency }}</small></span>
            </div>

            <div style="{{ $box }}; background: color-mix(in oklab, var(--primary-500) 12%, transparent); color: var(--primary-600)">
                <span style="{{ $boxLabel }}">{{ __('filament/resources/customer.actions.pay_debt.preview.paying') }}</span>
                <span style="{{ $boxValue }}">{{ $paying->number }} <small>{{ $paying->currency }}</small></span>
            </div>

            <div style="{{ $box }}; background: color-mix(in oklab, var(--{{ $isSettled ? 'success' : 'warning' }}-500) 12%, transparent); color: var(--{{ $isSettled ? 'success' : 'warning' }}-600)">
                <span style="{{ $boxLabel }}">{{ __('filament/resources/customer.actions.pay_debt.preview.after') }}</span>
                <span style="{{ $boxValue }}">{{ $after->number }} <small>{{ $after->currency }}</small></span>
            </div>
        </div>

        @if ($lines !== [])
            <div style="display: flex; flex-direction: column; gap: 0.5rem">
                <span style="font-size: 0.875rem; font-weight: 600">
                    {{ __('filament/resources/customer.actions.pay_debt.preview.spread') }}
                </span>

                @foreach ($lines as $line)
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; border: 1px solid color-mix(in oklab, var(--gray-500) 25%, transparent); border-radius: 0.5rem; padding: 0.5rem 0.75rem">
                        <div style="display: flex; flex-direction: column">
                            <span style="font-weight: 600">{{ $line['label'] }}</span>
                            <span style="font-size: 0.8125rem; opacity: 0.75">
                                {{ __('filament/resources/customer.actions.pay_debt.preview.order_remaining', ['amount' => "{$line['remaining']->number} {$line['remaining']->currency}"]) }}
                            </span>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem">
                            @if ($line['share'] !== null)
                                <span style="font-weight: 700; color: var(--success-600)">− {{ $line['share']->number }} {{ $line['share']->currency }}</span>
                            @endif

                            <span style="font-size: 0.75rem; font-weight: 600; border-radius: 9999px; padding: 0.125rem 0.5rem; background: color-mix(in oklab, var(--{{ $statusColors[$line['status']] }}-500) 15%, transparent); color: var(--{{ $statusColors[$line['status']] }}-600)">
                                {{ __('filament/resources/customer.actions.pay_debt.preview.'.$line['status']) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
