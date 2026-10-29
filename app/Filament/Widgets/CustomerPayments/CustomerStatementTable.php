<?php

namespace App\Filament\Widgets\CustomerPayments;

use App\Enums\OrderPaymentType;
use App\Filament\Forms\Components\DateInput;
use App\Filament\Resources\Orders\OrderResource;
use App\Metrics\Currency;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use App\Models\Order;
use App\Orders\WeeklyPaymentReminders;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * The account statement tab of the customer payments page. The customer and the period are chosen at the top and apply at once.
 * Under them, three cards sum the period (postpaid orders, payments, and the balance owed), then one line per movement:
 * a postpaid order adds to what the customer owes, and a payment takes from it. A down payment is a payment like any other,
 * only named apart so it can be told from the rest. Cash orders are paid when they are sold, so they are not in it.
 * With a from date, everything before it is summed into a previous balance on the first line.
 *
 * The statement opens by order: a tree with one branch per postpaid order (inside the period, by its date), with what it came to,
 * what was paid on it, what is left, and under it every payment that went to it, oldest first, with its date, what of it went to
 * this order, and what was left on the order after it. So each order shows how it was paid over time. "By date" shows the lines.
 */
class CustomerStatementTable extends TableWidget
{
    /**
     * The table is not added to the home dashboard on its own; the customer payments page shows it.
     */
    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.customer-statement-table';

    public int|string|array $columnSpan = 'full';

    /**
     * The customer the statement opens on, passed in by the page, or null to choose one.
     */
    public ?int $customerId = null;

    /**
     * The state of the customer and period form.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * How the statement is shown: "orders" (a tree of every order with its payments) or "dates" (one line per movement).
     */
    public string $layout = 'orders';

    public function mount(): void
    {
        $this->form->fill(['customer' => $this->customerId]);
    }

    /**
     * Reads the statement again when a payment is recorded or deleted on the page.
     */
    #[On('customer-payments-changed')]
    public function refreshStatement(): void
    {
        unset($this->statement);
        $this->resetTable();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['md' => 3])
                    ->schema([
                        Select::make('customer')
                            ->label(__('filament/resources/customer.statement.fields.customer'))
                            ->placeholder(__('filament/resources/customer.statement.fields.customer_placeholder'))
                            ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshStatement()),
                        DateInput::make('from')
                            ->label(__('filament/resources/customer.statement.fields.from'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshStatement()),
                        DateInput::make('until')
                            ->label(__('filament/resources/customer.statement.fields.until'))
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshStatement()),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->statement['rows'] ?? [])
            ->heading(null)
            ->striped()
            ->columns([
                TextColumn::make('date')
                    ->label(__('filament/resources/customer.statement.columns.date'))
                    ->date('Y/m/d'),
                TextColumn::make('movement')
                    ->label(__('filament/resources/customer.statement.columns.movement'))
                    ->badge()
                    ->color(fn (array $record): string => $record['color'])
                    ->icon(fn (array $record): Heroicon => $record['icon'])
                    ->description(fn (array $record): string => $record['details']),
                TextColumn::make('owed')
                    ->label(__('filament/resources/customer.statement.columns.owed'))
                    ->numeric()
                    ->prefix('+ ')
                    ->color('danger'),
                TextColumn::make('paid')
                    ->label(__('filament/resources/customer.statement.columns.paid'))
                    ->numeric()
                    ->prefix('− ')
                    ->color('success'),
                TextColumn::make('balance')
                    ->label(__('filament/resources/customer.statement.columns.balance'))
                    ->numeric()
                    ->weight(FontWeight::Bold)
                    ->color(fn (float $state): string => $state > 0.0001 ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (array $record): string => $record['is_receipt']
                        ? __('filament/resources/customer.statement.actions.open_receipt')
                        : __('filament/resources/customer.statement.actions.open_order'))
                    ->icon(fn (array $record): Heroicon => $record['is_receipt'] ? Heroicon::OutlinedPhoto : Heroicon::OutlinedShoppingBag)
                    ->color('gray')
                    ->url(fn (array $record): ?string => $record['url'])
                    ->openUrlInNewTab(fn (array $record): bool => $record['is_receipt'])
                    ->visible(fn (array $record): bool => filled($record['url'])),
            ])
            ->paginated(false)
            ->emptyStateIcon(fn (): Heroicon => $this->statement === null ? Heroicon::OutlinedUserCircle : Heroicon::OutlinedDocumentText)
            ->emptyStateHeading(fn (): string => $this->statement === null
                ? __('filament/resources/customer.statement.choose_customer')
                : __('filament/resources/customer.statement.empty'));
    }

    /**
     * @return array{summary: list<array{label: string, value: float, currency: Currency, color: string}>}
     */
    protected function getViewData(): array
    {
        return [
            'summary' => $this->statement['summary'] ?? [],
            'hasCustomer' => $this->statement !== null,
            'orderTree' => $this->layout === 'orders' ? $this->orderTree() : [],
        ];
    }

    /**
     * The statement of the chosen customer inside the period: its lines keyed from 1, each with the balance after it,
     * and the cards that sum the period. Null when no customer is chosen.
     *
     * @return array{rows: array<int, array<string, mixed>>, summary: list<array{label: string, value: float, currency: Currency, color: string}>}|null
     */
    #[Computed]
    public function statement(): ?array
    {
        $customerId = $this->data['customer'] ?? null;
        $customer = filled($customerId) ? Customer::query()->find($customerId) : null;

        if ($customer === null) {
            return null;
        }

        $from = filled($this->data['from'] ?? null) ? CarbonImmutable::parse($this->data['from'])->startOfDay() : null;
        $until = filled($this->data['until'] ?? null) ? CarbonImmutable::parse($this->data['until'])->endOfDay() : null;

        [$before, $inside] = $this->entries($customer)
            ->partition(fn (array $entry): bool => $from !== null && $entry['date']->lt($from));

        $inside = $inside->filter(fn (array $entry): bool => $until === null || $entry['date']->lte($until));
        $balance = (float) $before->sum(fn (array $entry): float => ($entry['owed'] ?? 0) - ($entry['paid'] ?? 0));
        $rows = [];

        if ($from !== null) {
            $rows[] = [
                'date' => $from,
                'movement' => __('filament/resources/customer.statement.types.opening_balance'),
                'details' => __('filament/resources/customer.statement.details.opening_balance', ['date' => $from->format('Y/m/d')]),
                'color' => 'gray',
                'icon' => Heroicon::OutlinedClock,
                'owed' => null,
                'paid' => null,
                'balance' => $balance,
                'url' => null,
                'is_receipt' => false,
            ];
        }

        foreach ($inside as $entry) {
            $balance += ($entry['owed'] ?? 0) - ($entry['paid'] ?? 0);
            $rows[] = [...$entry, 'balance' => $balance];
        }

        return [
            'rows' => collect($rows)->mapWithKeys(fn (array $row, int $index): array => [$index + 1 => $row])->all(),
            'summary' => [
                ['label' => __('filament/resources/customer.statement.summary.orders'), 'value' => (float) $inside->sum('owed'), 'currency' => Currency::IraqiDinar, 'color' => 'warning'],
                ['label' => __('filament/resources/customer.statement.summary.payments'), 'value' => (float) $inside->sum('paid'), 'currency' => Currency::IraqiDinar, 'color' => 'success'],
                ['label' => __('filament/resources/customer.statement.summary.balance'), 'value' => $balance, 'currency' => Currency::IraqiDinar, 'color' => $balance > 0.0001 ? 'danger' : 'success'],
            ],
        ];
    }

    /**
     * The chosen customer's postpaid orders inside the period, oldest first, each with what it came to, what was paid on it, what is
     * left, its weekly payment with what of it is late and the next one (for an order with a weekly payment), and every payment that
     * went to it, oldest first, with what was left on the order after it. Empty without a customer.
     *
     * @return list<array{id: int, date: string, items: int, total: float, paid: float, remaining: float, percent: int, url: string, weekly: ?float, late: float, late_days: ?int, next_amount: float, next_date: ?CarbonImmutable, payments: list<array{date: string, amount: float, left_after: float, is_down_payment: bool, user: ?string, notes: ?string, receipt: string}>}>
     */
    private function orderTree(): array
    {
        $customerId = $this->data['customer'] ?? null;
        $customer = filled($customerId) ? Customer::query()->find($customerId) : null;

        if ($customer === null) {
            return [];
        }

        $reminders = (new WeeklyPaymentReminders)->ordersOn(today())->keyBy(fn (array $row): int => $row['order']->id);

        return $customer->orders()
            ->where('payment_type', OrderPaymentType::Postpaid)
            ->when(filled($this->data['from'] ?? null), fn ($query) => $query->whereDate('date', '>=', $this->data['from']))
            ->when(filled($this->data['until'] ?? null), fn ($query) => $query->whereDate('date', '<=', $this->data['until']))
            ->withCount('items')
            ->with(['allocations.payment.user'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (Order $order) use ($reminders): array {
                $left = (float) $order->total_amount;
                $reminder = $reminders->get($order->id);
                $payments = $order->allocations
                    ->filter(fn (CustomerPaymentOrder $allocation): bool => $allocation->payment !== null)
                    ->sortBy(fn (CustomerPaymentOrder $allocation): string => $allocation->payment->date->toDateString().'|'.str_pad((string) $allocation->customer_payment_id, 12, '0', STR_PAD_LEFT))
                    ->map(function (CustomerPaymentOrder $allocation) use (&$left): array {
                        $left -= (float) $allocation->amount;

                        return [
                            'date' => $allocation->payment->date->format('Y/m/d'),
                            'amount' => (float) $allocation->amount,
                            'left_after' => max($left, 0),
                            'is_down_payment' => (bool) $allocation->payment->is_down_payment,
                            'user' => $allocation->payment->user?->name,
                            'notes' => $allocation->payment->notes,
                            'receipt' => route('customer-payments.receipt', $allocation->payment),
                        ];
                    })
                    ->values()
                    ->all();
                $total = (float) $order->total_amount;
                $paid = (float) $order->paid_amount;

                return [
                    'id' => $order->id,
                    'date' => $order->date->format('Y/m/d'),
                    'items' => (int) $order->items_count,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => (float) $order->remaining,
                    'percent' => $total > 0 ? (int) min(100, round($paid / $total * 100)) : 0,
                    'url' => OrderResource::getUrl('view', ['record' => $order]),
                    'weekly' => filled($order->weekly_payment) ? (float) $order->weekly_payment : null,
                    'late' => (float) ($reminder['late'] ?? 0),
                    'late_days' => isset($reminder['late_since']) ? (int) $reminder['late_since']->diffInDays(today()) : null,
                    'next_amount' => (float) ($reminder['next_amount'] ?? 0),
                    'next_date' => $reminder['next_due_date'] ?? null,
                    'payments' => $payments,
                ];
            })
            ->all();
    }

    /**
     * Every postpaid order and payment of the customer that is not deleted, from the oldest date to the newest.
     * On the same date, orders come before payments, each in the order they were created.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function entries(Customer $customer): Collection
    {
        $orders = $customer->orders()
            ->where('payment_type', OrderPaymentType::Postpaid)
            ->withCount('items')
            ->get()
            ->map(fn (Order $order): array => [
                'date' => $order->date->toImmutable(),
                'movement' => __('filament/resources/customer.statement.types.order'),
                'details' => __('filament/resources/customer.statement.details.order', ['id' => $order->id, 'items' => $order->items_count]),
                'color' => 'warning',
                'icon' => Heroicon::OutlinedShoppingBag,
                'owed' => $order->total_amount,
                'paid' => null,
                'url' => OrderResource::getUrl('view', ['record' => $order]),
                'is_receipt' => false,
                'sort' => [0, $order->id],
            ]);

        $payments = $customer->payments()
            ->with('user')
            ->get()
            ->map(fn (CustomerPayment $payment): array => [
                'date' => $payment->date->toImmutable(),
                'movement' => $payment->is_down_payment
                    ? __('filament/resources/customer.statement.types.down_payment')
                    : __('filament/resources/customer.statement.types.payment'),
                'details' => collect([
                    __('filament/resources/customer.statement.details.payment_by', ['user' => $payment->user?->name]),
                    $payment->notes,
                ])->filter()->implode(' · '),
                'color' => $payment->is_down_payment ? 'info' : 'success',
                'icon' => Heroicon::OutlinedBanknotes,
                'owed' => null,
                'paid' => $payment->amount,
                'url' => filled($payment->image) ? Storage::disk('public')->url($payment->image) : null,
                'is_receipt' => true,
                'sort' => [1, $payment->id],
            ]);

        return $orders
            ->concat($payments)
            ->sortBy([
                fn (array $first, array $second): int => [$first['date'], ...$first['sort']] <=> [$second['date'], ...$second['sort']],
            ])
            ->values();
    }
}
