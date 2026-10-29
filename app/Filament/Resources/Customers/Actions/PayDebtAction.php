<?php

namespace App\Filament\Resources\Customers\Actions;

use App\Filament\Forms\Components\DateInput;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Orders\PayCustomerDebt;
use App\Orders\WeeklyPaymentReminders;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Records one payment of a customer on the orders it is for: every postpaid order the customer still owes on has its own amount,
 * so each order keeps what was paid on it, and one payment can pay one order or several. Each order can be filled with what is
 * left on it. While the amounts are typed, the form shows the current debt, what is being paid, and what remains after it.
 * On a customer's row or page the customer is that record; on an order's page, or with an order in the arguments (a row of the
 * reminders), the form shows that order only. The action shows only for customers that are not deleted and have a debt, and for
 * orders that still have something left. Without a record, such as on the customer payments page, the form asks for the customer.
 * It shows only to users who can create customer payments. A rule the payment breaks is shown as a notification and the form
 * stays open. Once saved, the notification carries a button that opens the payment's receipt to print.
 */
class PayDebtAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'payDebt';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament/resources/customer.actions.pay_debt.label'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (?Model $record): bool => (auth()->user()?->can('create', CustomerPayment::class) ?? false) && $this->canBePaid($record))
            ->modalHeading(function (?Model $record, array $arguments): string {
                $order = $this->orderOf($record, $arguments);
                $customer = $this->customerOf($record, null);

                return match (true) {
                    $order !== null => __('filament/resources/customer.actions.pay_debt.heading_order', ['order' => $order->id, 'customer' => $order->customer?->name]),
                    $customer !== null => __('filament/resources/customer.actions.pay_debt.heading', ['customer' => $customer->name]),
                    default => __('filament/resources/customer.actions.pay_debt.label'),
                };
            })
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('filament/resources/customer.actions.pay_debt.submit'))
            ->schema(fn (?Model $record, array $arguments): array => [
                Select::make('customer_id')
                    ->label(__('filament/resources/customer.actions.pay_debt.customer'))
                    ->options(fn (): array => Customer::query()
                        ->whereHas('orders', fn (Builder $query): Builder => $query->debts())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('amounts', []))
                    ->required()
                    ->visible($this->customerOf($record, null) === null),
                Group::make(fn (Get $get): array => $this->orderFields($this->ordersOf($record, $arguments, $get('customer_id'))))
                    ->statePath('amounts'),
                View::make('filament.customers.pay-debt-preview')
                    ->viewData(fn (Get $get): array => $this->preview($record, $arguments, $get('customer_id'), $get('amounts')))
                    ->columnSpanFull(),
                DateInput::make('date')
                    ->label(__('filament/resources/customer.actions.pay_debt.date'))
                    ->defaultToday()
                    ->required(),
                FileUpload::make('image')
                    ->label(__('filament/resources/customer.actions.pay_debt.image'))
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('customer-payments')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('filament/resources/customer.actions.pay_debt.notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, ?Model $record, Action $action, Component $livewire): void {
                try {
                    $payment = (new PayCustomerDebt)->payOrders(
                        auth()->user(),
                        $this->customerOf($record, $data['customer_id'] ?? null) ?? Customer::query()->findOrFail($data['customer_id'] ?? null),
                        $this->amountsOf($data['amounts'] ?? []),
                        $data['date'],
                        $data['image'] ?? null,
                        $data['notes'] ?? null,
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first())
                        ->send();

                    $action->halt();
                }

                $livewire->dispatch('customer-payments-changed');

                Notification::make()
                    ->title(__('filament/resources/customer.actions.pay_debt.done'))
                    ->success()
                    ->actions([
                        Action::make('printReceipt')
                            ->label(__('filament/resources/customer_payment.actions.print_receipt'))
                            ->icon(Heroicon::OutlinedPrinter)
                            ->button()
                            ->url(route('customer-payments.receipt', $payment), shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    /**
     * One amount field for each order: its number and date, and under it what is left on it, what of it is late, and for an order
     * with a weekly payment the weekly payment and the next one with its date; and a button that fills it with what is left on it.
     *
     * @param  list<Order>  $orders
     * @return list<TextInput>
     */
    private function orderFields(array $orders): array
    {
        $reminders = (new WeeklyPaymentReminders)->ordersOn(today())->keyBy(fn (array $row): int => $row['order']->id);

        return array_map(fn (Order $order): TextInput => $this->amountInput("order_{$order->id}")
            ->label(__('filament/resources/customer.actions.pay_debt.preview.order', ['id' => $order->id, 'date' => $order->date->format('Y/m/d')]))
            ->placeholder('0')
            ->helperText($this->orderFacts($order, $reminders->get($order->id)))
            ->maxValue((float) $order->remaining)
            ->live(debounce: 500)
            ->suffixAction(
                Action::make("payOrder{$order->id}")
                    ->label(__('filament/resources/customer.actions.pay_debt.pay_order_all'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->action(fn (Set $set): mixed => $set("order_{$order->id}", $this->money((float) $order->remaining))),
            ), $orders);
    }

    /**
     * Under an order's field: what is left on it, what of it is late, and its weekly payment with the next one and its date.
     *
     * @param  array{late: float, next_due_date: ?CarbonImmutable, next_amount: float}|null  $reminder
     */
    private function orderFacts(Order $order, ?array $reminder): string
    {
        $key = 'filament/resources/customer.actions.pay_debt.';
        $next = $reminder['next_due_date'] ?? null;

        return collect([
            __($key.'preview.order_remaining', ['amount' => $this->money((float) $order->remaining)]),
            ($reminder['late'] ?? 0) > 0.0001 ? __($key.'order_late', ['amount' => $this->money($reminder['late'])]) : null,
            filled($order->weekly_payment) ? __($key.'order_weekly', ['amount' => $this->money((float) $order->weekly_payment)]) : null,
            match (true) {
                $next === null => null,
                $next->isToday() => __($key.'order_next_today', ['amount' => $this->money($reminder['next_amount'])]),
                default => __($key.'order_next', ['amount' => $this->money($reminder['next_amount']), 'date' => $next->format('Y/m/d')]),
            },
        ])->filter()->implode(' · ');
    }

    /**
     * An amount field with thousands separators while typing, like the panel's money field, but kept as the typed text: a number
     * field turns "30,000" into 30 when a button inside the form is pressed, which would change the amounts already typed.
     * The separators are taken out for validation, and the amounts are read as numbers when they are used.
     */
    private function amountInput(string $name): TextInput
    {
        return TextInput::make($name)
            ->mask(RawJs::make('$money($input)'))
            ->stripCharacters(',')
            ->inputMode('decimal')
            ->rule('numeric')
            ->minValue(0);
    }

    /**
     * Whether there is something to pay on the record: a customer that is not deleted and owes, an order that still has something
     * left, or no record at all (the customer is chosen in the form).
     */
    private function canBePaid(?Model $record): bool
    {
        return match (true) {
            $record instanceof Customer => ! $record->trashed() && $record->debt() > 0,
            $record instanceof Order => $this->openOrder($record) !== null,
            default => true,
        };
    }

    /**
     * The customer of the record (a customer, or the customer of an order), or the customer chosen in the form when there is none.
     */
    private function customerOf(?Model $record, mixed $customerId): ?Customer
    {
        return match (true) {
            $record instanceof Customer => $record,
            $record instanceof Order => $record->customer,
            filled($customerId) => Customer::query()->find($customerId),
            default => null,
        };
    }

    /**
     * The order the form is opened on: the order of the page, or the order in the arguments.
     */
    private function orderOf(?Model $record, array $arguments): ?Order
    {
        if ($record instanceof Order) {
            return $record;
        }

        return filled($arguments['order'] ?? null) ? Order::query()->find($arguments['order']) : null;
    }

    /**
     * The orders the form pays: the order it is opened on when there is one, else every order the customer still owes on, oldest first.
     *
     * @return list<Order>
     */
    private function ordersOf(?Model $record, array $arguments, mixed $customerId): array
    {
        $customer = $this->customerOf($record, $customerId);

        if ($customer === null) {
            return [];
        }

        $orders = array_map(fn (array $line): Order => $line['order'], (new PayCustomerDebt)->plan($customer, $customer->debt()));
        $order = $this->orderOf($record, $arguments);

        return $order === null ? $orders : array_values(array_filter($orders, fn (Order $open): bool => $open->id === $order->id));
    }

    /**
     * The order as one of its customer's debts, or null when nothing is left on it.
     */
    private function openOrder(Order $order): ?Order
    {
        return Order::query()->debts()->whereKey($order->getKey())->first();
    }

    /**
     * The typed amounts as the payment takes them: [order id => amount], without the orders left empty.
     *
     * @param  array<string, mixed>  $amounts
     * @return array<int, float>
     */
    private function amountsOf(array $amounts): array
    {
        return collect($amounts)
            ->mapWithKeys(fn (mixed $amount, string $key): array => [(int) str_replace('order_', '', $key) => $this->number($amount)])
            ->filter(fn (float $amount): bool => $amount > 0)
            ->all();
    }

    /**
     * What the preview shows: the current debt, the amounts being paid together, and what remains after them.
     * Without a customer, only a hint to choose one.
     *
     * @return array{hasCustomer: bool, debt?: FormattedValue, paying?: FormattedValue, after?: FormattedValue, isSettled?: bool, lines?: list<mixed>}
     */
    private function preview(?Model $record, array $arguments, mixed $customerId, mixed $amounts): array
    {
        $customer = $this->customerOf($record, $customerId);

        if ($customer === null) {
            return ['hasCustomer' => false];
        }

        $debt = $customer->debt();
        $paying = min(array_sum($this->amountsOf(is_array($amounts) ? $amounts : [])), $debt);

        return [
            'hasCustomer' => true,
            'debt' => FormattedValue::of($debt, Currency::IraqiDinar),
            'paying' => FormattedValue::of($paying, Currency::IraqiDinar),
            'after' => FormattedValue::of($debt - $paying, Currency::IraqiDinar),
            'isSettled' => $debt - $paying <= 0.0001,
            'lines' => [],
        ];
    }

    /**
     * A typed amount as a number, without its thousands separators.
     */
    private function number(mixed $amount): float
    {
        return max((float) str_replace(',', '', (string) $amount), 0);
    }

    /**
     * An amount as the money field shows it: whole dinars with thousands separators.
     */
    private function money(float $amount): string
    {
        return number_format($amount, 0, '.', ',');
    }
}
