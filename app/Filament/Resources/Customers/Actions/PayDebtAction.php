<?php

namespace App\Filament\Resources\Customers\Actions;

use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Orders\PayCustomerDebt;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Records one payment of a customer, spread on its postpaid orders from the oldest to the newest.
 * On a customer's row or page the customer is that record, and the action shows only for customers that are not deleted and have a debt.
 * Without a record, such as on the customer payments page, the form asks for the customer, from the customers that have a debt.
 * While the amount is typed, the form shows the current debt, what remains after the payment, and which orders the amount goes to.
 * It shows only to users who can create customer payments. A rule the payment breaks is shown as a notification and the form stays open.
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
            ->visible(fn (?Customer $record): bool => (auth()->user()?->can('create', CustomerPayment::class) ?? false)
                && ($record === null || (! $record->trashed() && $record->debt() > 0)))
            ->modalHeading(fn (?Customer $record): string => $record === null
                ? __('filament/resources/customer.actions.pay_debt.label')
                : __('filament/resources/customer.actions.pay_debt.heading', ['customer' => $record->name]))
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('filament/resources/customer.actions.pay_debt.submit'))
            ->schema(fn (?Customer $record): array => [
                Select::make('customer_id')
                    ->label(__('filament/resources/customer.actions.pay_debt.customer'))
                    ->options(fn (): array => Customer::query()
                        ->whereHas('orders', fn (Builder $query): Builder => $query->debts())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('amount', null))
                    ->required()
                    ->visible($record === null),
                MoneyInput::make('amount')
                    ->label(__('filament/resources/customer.actions.pay_debt.amount'))
                    ->minValue(0.01)
                    ->maxValue(fn (Get $get): ?float => ($customer = $this->customerOf($record, $get('customer_id'))) === null
                        ? null
                        : (new PayCustomerDebt)->debtOf($customer))
                    ->live(debounce: 500)
                    ->hintAction(
                        Action::make('payAll')
                            ->label(__('filament/resources/customer.actions.pay_debt.pay_all'))
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->visible(fn (Get $get): bool => $this->customerOf($record, $get('customer_id')) !== null)
                            ->action(fn (Get $get, Set $set): mixed => $set(
                                'amount',
                                number_format((new PayCustomerDebt)->debtOf($this->customerOf($record, $get('customer_id'))), 0, '.', ','),
                            )),
                    )
                    ->required(),
                View::make('filament.customers.pay-debt-preview')
                    ->viewData(fn (Get $get): array => $this->preview($record, $get('customer_id'), $get('amount')))
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
            ->action(function (array $data, ?Customer $record, Action $action, Component $livewire): void {
                try {
                    (new PayCustomerDebt)->handle(
                        auth()->user(),
                        $this->customerOf($record, $data['customer_id'] ?? null) ?? Customer::query()->findOrFail($data['customer_id'] ?? null),
                        (float) $data['amount'],
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
                    ->send();
            });
    }

    /**
     * The customer of the record, or the customer chosen in the form when there is no record.
     */
    private function customerOf(?Customer $record, mixed $customerId): ?Customer
    {
        return $record ?? (filled($customerId) ? Customer::query()->find($customerId) : null);
    }

    /**
     * What the preview under the amount shows: the current debt, the amount being paid, what remains after it,
     * and every order the customer still owes on, oldest first, with what the typed amount pays on it:
     * fully, partly, or nothing yet. Without a customer, only a hint to choose one.
     *
     * @return array{hasCustomer: bool, debt?: FormattedValue, paying?: FormattedValue, after?: FormattedValue, isSettled?: bool, lines?: list<array{label: string, remaining: FormattedValue, share: ?FormattedValue, status: string}>}
     */
    private function preview(?Customer $record, mixed $customerId, mixed $amount): array
    {
        $customer = $this->customerOf($record, $customerId);

        if ($customer === null) {
            return ['hasCustomer' => false];
        }

        $payCustomerDebt = new PayCustomerDebt;
        $debt = $payCustomerDebt->debtOf($customer);
        $paying = min(max((float) str_replace(',', '', (string) $amount), 0), $debt);
        $shares = collect($payCustomerDebt->plan($customer, $paying))
            ->mapWithKeys(fn (array $line): array => [$line['order']->id => $line['share']]);

        return [
            'hasCustomer' => true,
            'debt' => FormattedValue::of($debt, Currency::IraqiDinar),
            'paying' => FormattedValue::of($paying, Currency::IraqiDinar),
            'after' => FormattedValue::of($debt - $paying, Currency::IraqiDinar),
            'isSettled' => $debt - $paying <= 0.0001,
            'lines' => array_map(function (array $line) use ($shares): array {
                $remaining = (float) $line['order']->remaining;
                $share = (float) ($shares[$line['order']->id] ?? 0);

                return [
                    'label' => __('filament/resources/customer.actions.pay_debt.preview.order', [
                        'id' => $line['order']->id,
                        'date' => $line['order']->date->format('Y/m/d'),
                    ]),
                    'remaining' => FormattedValue::of($remaining, Currency::IraqiDinar),
                    'share' => $share > 0 ? FormattedValue::of($share, Currency::IraqiDinar) : null,
                    'status' => match (true) {
                        $share >= $remaining - 0.0001 => 'full',
                        $share > 0 => 'partial',
                        default => 'untouched',
                    },
                ];
            }, $payCustomerDebt->plan($customer, $debt)),
        ];
    }
}
