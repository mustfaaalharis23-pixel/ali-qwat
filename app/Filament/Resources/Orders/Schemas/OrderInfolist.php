<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderPaymentType;
use App\Models\CustomerPaymentOrder;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Orders\WeeklyPaymentReminders;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class OrderInfolist
{
    /**
     * The details page of an order: its information, amounts (what the customer owes written large first), lines, and notes.
     * What the order cost us and what it earned, on the order and on each line, show only to a user
     * who has the item.view_cost_price permission.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/order.sections.details'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 5])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label(__('filament/resources/order.fields.customer_id')),
                        TextEntry::make('date')
                            ->label(__('filament/resources/order.fields.date'))
                            ->date('Y/m/d'),
                        TextEntry::make('payment_type')
                            ->label(__('filament/resources/order.fields.payment_type'))
                            ->badge(),
                        TextEntry::make('cashier.name')
                            ->label(__('filament/resources/order.fields.cashier_id')),
                        TextEntry::make('user.name')
                            ->label(__('filament/resources/order.fields.user_id')),
                    ]),
                // What the customer owes is written large: the total, what was paid, and what is left, red while something is owed.
                Section::make(__('filament/resources/order.sections.amounts'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('total_amount')
                            ->label(__('filament/resources/order.fields.total_amount'))
                            ->numeric()
                            ->badge()
                            ->color('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('paid_amount')
                            ->label(__('filament/resources/order.fields.paid_amount'))
                            ->numeric()
                            ->badge()
                            ->color('success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('remaining')
                            ->label(__('filament/resources/order.fields.remaining'))
                            ->numeric()
                            ->badge()
                            ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('profit')
                            ->label(__('filament/resources/order.fields.profit'))
                            ->numeric()
                            ->badge()
                            ->color('success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->visible(fn (): bool => self::canSeeTheCost()),
                        TextEntry::make('amount')
                            ->label(__('filament/resources/order.fields.amount'))
                            ->numeric(),
                        // What the order's discount took off its amount, on an order that has one.
                        TextEntry::make('discount')
                            ->label(__('filament/resources/order.fields.discount'))
                            ->state(fn (Order $record): float => (float) $record->amount - (float) $record->total_amount)
                            ->numeric()
                            ->color('danger')
                            ->visible(fn (Order $record): bool => (float) $record->amount - (float) $record->total_amount > 0.0001),
                        TextEntry::make('down_payment')
                            ->label(__('filament/resources/order.fields.down_payment'))
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('weekly_payment')
                            ->label(__('filament/resources/order.fields.weekly_payment'))
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('total_cost')
                            ->label(__('filament/resources/order.fields.total_cost'))
                            ->numeric()
                            ->visible(fn (): bool => self::canSeeTheCost()),
                    ]),
                // A postpaid order with a weekly payment shows what is due on it now: what is late and since how many days, the next
                // weekly payment with its date, and what is left with how many weekly payments, until it is fully paid.
                Section::make(__('filament/resources/order.fields.weekly_payment'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                    ->columnSpanFull()
                    ->visible(fn (Order $record): bool => ! $record->trashed() && $record->payment_type === OrderPaymentType::Postpaid && filled($record->weekly_payment))
                    ->schema([
                        TextEntry::make('weekly_payment')
                            ->label(__('filament/resources/order.weekly.amount'))
                            ->numeric()
                            ->weight(FontWeight::Bold),
                        TextEntry::make('weekly_late')
                            ->label(__('filament/pages/customer_payments.reminders.late_amount'))
                            ->state(fn (Order $record): string => self::weeklyLateText($record))
                            ->badge()
                            ->color(fn (Order $record): string => (self::reminderOf($record)['late'] ?? 0) > 0.0001 ? 'danger' : 'success'),
                        TextEntry::make('weekly_next')
                            ->label(__('filament/resources/order.weekly.next'))
                            ->state(fn (Order $record): string => self::weeklyNextText($record))
                            ->badge()
                            ->color(fn (Order $record): string => self::reminderOf($record)['next_due_date']?->isToday() ? 'warning' : 'gray'),
                        TextEntry::make('weekly_left')
                            ->label(__('filament/resources/order.weekly.left'))
                            ->state(fn (Order $record): string => ($reminder = self::reminderOf($record)) === null
                                ? __('filament/resources/order.weekly.settled')
                                : __('filament/resources/order.weekly.left_value', [
                                    'amount' => Number::format($reminder['remaining'], maxPrecision: 2),
                                    'weeks' => $reminder['weeks_left'],
                                ])),
                    ]),
                Section::make(__('filament/resources/order.fields.items'))
                    ->icon(Heroicon::OutlinedQueueList)
                    ->columnSpanFull()
                    ->schema([
                        self::linesEntry(),
                    ]),
                // What was paid on a postpaid order, payment by payment, so the order shows how much of it was paid and when.
                Section::make(__('filament/resources/order.sections.payments'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columnSpanFull()
                    ->visible(fn (Order $record): bool => $record->payment_type === OrderPaymentType::Postpaid)
                    ->schema([
                        self::paymentsEntry(),
                    ]),
                TextEntry::make('notes')
                    ->label(__('filament/resources/order.fields.notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The order's lines as a table, with the lens power a line was sold at and the colors of its packets as badges under its item,
     * and its dozens with the packets and pieces they are under them.
     * What each line cost us and earned shows only to a user who can see the cost.
     */
    private static function linesEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->hiddenLabel()
            ->table(array_values(array_filter([
                TableColumn::make(__('filament/resources/order.fields.item'))->width('40%'),
                TableColumn::make(__('filament/resources/order.fields.quantity')),
                TableColumn::make(__('filament/resources/order.fields.price')),
                TableColumn::make(__('filament/resources/order.fields.total_price')),
                self::canSeeTheCost() ? TableColumn::make(__('filament/resources/order.fields.cost_price')) : null,
                self::canSeeTheCost() ? TableColumn::make(__('filament/resources/order.fields.profit')) : null,
            ])))
            ->schema(array_values(array_filter([
                TextEntry::make('item.name')
                    ->belowContent(fn (OrderItem $record): array => array_values(array_filter([
                        $record->lensPower === null ? null : Text::make(
                            __('filament/resources/purchase_invoice.lines_below.power', ['value' => $record->lensPower->value]),
                        )->badge()->color('info'),
                        $record->colors === null ? null : Text::make($record->colorsText())->badge()->color('gray'),
                    ]))),
                TextEntry::make('quantity')
                    ->numeric(maxDecimalPlaces: 2)
                    ->belowContent(fn (OrderItem $record): Text => Text::make(__('filament/resources/order.packets_and_pieces', [
                        'packets' => Number::format($record->packets(), maxPrecision: 2),
                        'pieces' => Number::format((float) $record->pieces),
                    ]))->size(TextSize::ExtraSmall)->color('gray')),
                TextEntry::make('price')->numeric(),
                TextEntry::make('total_price')->numeric(),
                self::canSeeTheCost() ? TextEntry::make('cost_price')->numeric() : null,
                self::canSeeTheCost() ? TextEntry::make('profit')->numeric()->color('success') : null,
            ])));
    }

    /**
     * The payments on the order, oldest first, as a table: the date, what of the payment went to this order, who recorded it, whether
     * it was the down payment, and its receipt to print. A deleted payment is not on the order anymore.
     */
    private static function paymentsEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('allocations')
            ->hiddenLabel()
            ->state(fn (Order $record) => $record->allocations()->with('payment.user')->get()
                ->filter(fn (CustomerPaymentOrder $allocation): bool => $allocation->payment !== null)
                ->sortBy(fn (CustomerPaymentOrder $allocation): string => $allocation->payment->date->toDateString().'|'.str_pad((string) $allocation->id, 12, '0', STR_PAD_LEFT))
                ->values())
            ->placeholder(__('filament/resources/order.payments.none'))
            ->table([
                TableColumn::make(__('filament/resources/order.payments.date')),
                TableColumn::make(__('filament/resources/order.payments.amount')),
                TableColumn::make(__('filament/resources/order.payments.by')),
                TableColumn::make(__('filament/resources/order.payments.receipt')),
            ])
            ->schema([
                TextEntry::make('payment.date')
                    ->date('Y/m/d')
                    ->belowContent(fn (CustomerPaymentOrder $record): ?Text => $record->payment->is_down_payment
                        ? Text::make(__('filament/resources/order.payments.down_payment'))->badge()->color('info')
                        : null),
                TextEntry::make('amount')->numeric()->weight(FontWeight::Bold)->color('success'),
                TextEntry::make('payment.user.name')->placeholder('—'),
                TextEntry::make('receipt')
                    ->state(__('filament/resources/customer_payment.actions.print_receipt'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('primary')
                    ->url(fn (CustomerPaymentOrder $record): string => route('customer-payments.receipt', $record->payment), shouldOpenInNewTab: true),
            ]);
    }

    /**
     * The order's weekly payment reminder today (see WeeklyPaymentReminders::ordersOn), or null when nothing is left on it.
     *
     * @return array{order: Order, late: float, late_since: ?CarbonImmutable, next_due_date: ?CarbonImmutable, next_amount: float, remaining: float, weeks_left: int}|null
     */
    private static function reminderOf(Order $record): ?array
    {
        return once(fn (): ?array => (new WeeklyPaymentReminders)->ordersOn(today())
            ->first(fn (array $row): bool => $row['order']->is($record)));
    }

    /**
     * What is late on the order and since how many days, or that it is not late.
     */
    private static function weeklyLateText(Order $record): string
    {
        $reminder = self::reminderOf($record);

        if ($reminder === null || $reminder['late'] <= 0.0001) {
            return __('filament/pages/customer_payments.order_reminders.not_late');
        }

        return __('filament/resources/order.weekly.late_value', [
            'amount' => Number::format($reminder['late'], maxPrecision: 2),
            'days' => (int) $reminder['late_since']->diffInDays(today()),
        ]);
    }

    /**
     * The next weekly payment with its amount and date ("today" when it is due today), or that every payment already fell due,
     * or that the order is fully paid.
     */
    private static function weeklyNextText(Order $record): string
    {
        $reminder = self::reminderOf($record);
        $next = $reminder['next_due_date'] ?? null;

        return match (true) {
            $reminder === null => __('filament/resources/order.weekly.settled'),
            $next === null => __('filament/pages/customer_payments.order_reminders.no_next'),
            $next->isToday() => __('filament/resources/order.weekly.next_today', ['amount' => Number::format($reminder['next_amount'], maxPrecision: 2)]),
            default => __('filament/resources/order.weekly.next_value', [
                'amount' => Number::format($reminder['next_amount'], maxPrecision: 2),
                'date' => $next->format('Y/m/d'),
            ]),
        };
    }

    /**
     * Whether the user is allowed to see what the items cost us, and so the profit worked out from it.
     */
    private static function canSeeTheCost(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }
}
