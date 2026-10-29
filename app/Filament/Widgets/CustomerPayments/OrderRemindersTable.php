<?php

namespace App\Filament\Widgets\CustomerPayments;

use App\Filament\Pages\CustomerPayments;
use App\Filament\Resources\Customers\Actions\PayDebtAction;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Orders\WeeklyPaymentReminders;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * The weekly payment reminders on the home dashboard: one row per customer who has a postpaid order with a weekly payment that
 * is not fully paid, with what they are late in all and since how many days, and what is left on them. Under each customer,
 * every such order has its own reminder until it is fully paid: its weekly payment, what is late and since how many days,
 * the next weekly payment (its amount and date), and what is left with how many weekly payments. Three tabs pick the orders:
 * due today (the weekly payments that fall due today, with what is due today), late (the orders with unpaid weekly payments that
 * fell due before today, the oldest late first), and all of them (the late first, then by the nearest next payment).
 * Each customer can be called, paid for, or opened on their statement from their row. It shows to whoever can view the customer payments.
 */
class OrderRemindersTable extends TableWidget
{
    /**
     * The table is placed on the home dashboard by the panel, after the welcome card.
     */
    protected static bool $isDiscovered = false;

    public int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.order-reminders-table';

    /**
     * The tab the table is on: "today" (due today), "late", or "all".
     */
    public string $tab = 'today';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', CustomerPayment::class);
    }

    /**
     * Reads the reminders again when a payment is recorded from the table.
     */
    #[On('customer-payments-changed')]
    public function refreshReminders(): void
    {
        unset($this->allReminders, $this->reminders);
        $this->resetTable();
    }

    /**
     * Shows the orders of the tab chosen.
     */
    public function updatedTab(): void
    {
        unset($this->reminders);
        $this->resetTable();
    }

    /**
     * How many orders each tab has, for the badges of the tabs.
     *
     * @return array{today: int, late: int, all: int}
     */
    protected function getViewData(): array
    {
        return [
            'counts' => [
                'today' => $this->allReminders->filter(fn (array $row): bool => $this->isDueToday($row))->count(),
                'late' => $this->allReminders->filter(fn (array $row): bool => $this->isLate($row))->count(),
                'all' => $this->allReminders->count(),
            ],
        ];
    }

    /**
     * Today's reminders of every order with a weekly payment that is not fully paid.
     *
     * @return Collection<int, array{order: Order, late: float, late_since: ?CarbonImmutable, next_due_date: ?CarbonImmutable, next_amount: float, remaining: float, weeks_left: int}>
     */
    #[Computed]
    public function allReminders(): Collection
    {
        return (new WeeklyPaymentReminders)->ordersOn(today());
    }

    /**
     * The reminders of the tab's orders, grouped by customer in the order they are shown.
     *
     * @return Collection<int, Collection<int, array{order: Order, late: float, late_since: ?CarbonImmutable, next_due_date: ?CarbonImmutable, next_amount: float, remaining: float, weeks_left: int}>>
     */
    #[Computed]
    public function reminders(): Collection
    {
        return $this->allReminders
            ->filter(fn (array $row): bool => match ($this->tab) {
                'today' => $this->isDueToday($row),
                'late' => $this->isLate($row),
                default => true,
            })
            ->groupBy(fn (array $row): int => $row['order']->customer_id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $ids = $this->reminders->keys()->all();

                return Customer::query()
                    ->whereKey($ids)
                    ->when($ids !== [], fn (Builder $query): Builder => $query->orderByRaw(
                        'case id '.collect($ids)->map(fn (int $id, int $position): string => "when {$id} then {$position}")->implode(' ').' end',
                    ));
            })
            ->heading(__('filament/pages/customer_payments.order_reminders.heading'))
            ->description(__('filament/pages/customer_payments.order_reminders.description'))
            ->striped()
            ->paginated([10, 25, 50])
            // Each customer is one card that fits the screen, so the table never scrolls sideways (see the view): the customer and
            // their phone, what is due today, what is late and for how many days, and what is left on one line with the buttons,
            // then their orders under it.
            ->columns([
                Stack::make([
                    ViewColumn::make('card')
                        ->view('filament.widgets.order-reminder-card')
                        ->state(fn (Customer $record): array => [
                            'due_today' => (float) $this->remindersOf($record)->filter(fn (array $row): bool => $this->isDueToday($row))->sum('next_amount'),
                            'late' => (float) $this->remindersOf($record)->sum('late'),
                            'days_late' => $this->daysLate($record),
                            'remaining' => (float) $this->remindersOf($record)->sum('remaining'),
                            'orders' => $this->remindersOf($record)->all(),
                            'shows_due_today' => $this->tab === 'today',
                            'statement_url' => CustomerPayments::getUrl(['customer' => $record->id]),
                        ]),
                ]),
            ])
            // The card has its own buttons; the payment window is kept here, out of sight, so the card's buttons can open it.
            ->recordActions([
                PayDebtAction::make()->extraAttributes(['style' => 'display: none']),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading(fn (): string => match ($this->tab) {
                'today' => __('filament/pages/customer_payments.order_reminders.empty_today'),
                'late' => __('filament/pages/customer_payments.order_reminders.empty_late'),
                default => __('filament/pages/customer_payments.order_reminders.empty'),
            });
    }

    /**
     * The reminders of the customer's orders.
     *
     * @return Collection<int, array{order: Order, late: float, late_since: ?CarbonImmutable, next_due_date: ?CarbonImmutable, next_amount: float, remaining: float, weeks_left: int}>
     */
    private function remindersOf(Customer $record): Collection
    {
        return $this->reminders->get($record->id) ?? collect();
    }

    /**
     * Whether a weekly payment of the order falls due today.
     *
     * @param  array{next_due_date: ?CarbonImmutable, next_amount: float}  $row
     */
    private function isDueToday(array $row): bool
    {
        return ($row['next_due_date']?->isToday() ?? false) && $row['next_amount'] > 0.0001;
    }

    /**
     * Whether the order has weekly payments that fell due before today and are not paid.
     *
     * @param  array{late: float}  $row
     */
    private function isLate(array $row): bool
    {
        return $row['late'] > 0.0001;
    }

    /**
     * The days since the oldest weekly payment the customer is late on, or null when they are not late.
     */
    private function daysLate(Customer $record): ?int
    {
        $since = $this->remindersOf($record)->pluck('late_since')->filter()->min();

        return $since === null ? null : (int) $since->diffInDays(today());
    }
}
