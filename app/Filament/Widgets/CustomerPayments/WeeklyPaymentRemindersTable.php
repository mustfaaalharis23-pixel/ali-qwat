<?php

namespace App\Filament\Widgets\CustomerPayments;

use App\Filament\Pages\CustomerPayments;
use App\Filament\Resources\Customers\Actions\PayDebtAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Orders\WeeklyPaymentReminders;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * A reminders tab of the customer payments page: the customers who owe a weekly payment today, or, when late,
 * the customers with weekly payments that fell due before today and are still unpaid, with the date of the oldest
 * and the days since. Each customer can be called, paid for, or opened on their statement from their row.
 */
class WeeklyPaymentRemindersTable extends TableWidget
{
    /**
     * The table is not added to the home dashboard on its own; the customer payments page shows it.
     */
    protected static bool $isDiscovered = false;

    public int|string|array $columnSpan = 'full';

    /**
     * Whether the tab shows the late weekly payments instead of today's, passed in by the page.
     */
    public bool $late = false;

    /**
     * Reads the reminders again when a payment is recorded or deleted on the page.
     */
    #[On('customer-payments-changed')]
    public function refreshReminders(): void
    {
        unset($this->reminders);
        $this->resetTable();
    }

    /**
     * Today's or the late reminders, keyed by the customer's id, in the order they are shown.
     *
     * @return Collection<int, array{customer: Customer, amount: float, due_date: CarbonImmutable}>
     */
    #[Computed]
    public function reminders(): Collection
    {
        $reminders = new WeeklyPaymentReminders;

        return ($this->late ? $reminders->lateOn(today()) : $reminders->dueOn(today()))
            ->keyBy(fn (array $row): int => $row['customer']->id);
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
            ->heading(null)
            ->striped()
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament/pages/customer_payments.reminders.customer'))
                    ->weight(FontWeight::SemiBold)
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record])),
                TextColumn::make('phone')
                    ->label(__('filament/pages/customer_payments.reminders.phone'))
                    ->icon(Heroicon::OutlinedPhone)
                    ->url(fn (Customer $record): ?string => filled($record->phone) ? "tel:{$record->phone}" : null)
                    ->placeholder('-'),
                TextColumn::make('reminder_amount')
                    ->label($this->late
                        ? __('filament/pages/customer_payments.reminders.late_amount')
                        : __('filament/pages/customer_payments.reminders.due_amount'))
                    ->state(fn (Customer $record): float => $this->reminders[$record->id]['amount'] ?? 0)
                    ->numeric()
                    ->badge()
                    ->color($this->late ? 'danger' : 'warning')
                    ->icon(Heroicon::OutlinedBanknotes),
                TextColumn::make('reminder_due_date')
                    ->label(__('filament/pages/customer_payments.reminders.due_since'))
                    ->state(fn (Customer $record): ?string => $this->reminders[$record->id]['due_date']?->toDateString())
                    ->date('Y/m/d')
                    ->visible($this->late),
                TextColumn::make('reminder_days_late')
                    ->label(__('filament/pages/customer_payments.reminders.days_late'))
                    ->state(fn (Customer $record): int => (int) $this->reminders[$record->id]['due_date']->diffInDays(today()))
                    ->formatStateUsing(fn (int $state): string => __('filament/pages/customer_payments.reminders.days', ['days' => $state]))
                    ->color('danger')
                    ->weight(FontWeight::Bold)
                    ->visible($this->late),
            ])
            ->recordActions([
                PayDebtAction::make(),
                Action::make('statement')
                    ->label(__('filament/resources/customer.actions.statement'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->url(fn (Customer $record): string => CustomerPayments::getUrl(['customer' => $record->id])),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading($this->late
                ? __('filament/pages/customer_payments.reminders.empty_late')
                : __('filament/pages/customer_payments.reminders.empty_due'));
    }
}
