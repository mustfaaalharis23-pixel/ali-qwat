<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\Actions\PayDebtAction;
use App\Filament\Widgets\CustomerPayments\CustomerPaymentsTable;
use App\Filament\Widgets\CustomerPayments\CustomerStatementTable;
use App\Filament\Widgets\CustomerPayments\WeeklyPaymentRemindersTable;
use App\Models\CustomerPayment;
use App\Orders\WeeklyPaymentReminders;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * The work on customer payments in one page, one tab per job: the payments, the account statement of a customer,
 * and the reminders of the weekly payments due today and of the late ones, each with how many customers it holds.
 * A new payment is recorded from the page header. Opening the page with a customer opens that customer's statement.
 */
class CustomerPayments extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $slug = 'customer-payments';

    /**
     * The customer whose statement the page opens on, from the address: customer-payments?customer=5.
     */
    #[Url]
    public ?int $customer = null;

    /**
     * Anyone who can view customer payments can open the page.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', CustomerPayment::class);
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.sales');
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/pages/customer_payments.title');
    }

    public function getTitle(): string
    {
        return __('filament/pages/customer_payments.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            PayDebtAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->tabs([
                        Tab::make(__('filament/pages/customer_payments.tabs.payments'))
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Livewire::make(CustomerPaymentsTable::class),
                            ]),
                        Tab::make(__('filament/pages/customer_payments.tabs.statement'))
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Livewire::make(CustomerStatementTable::class, ['customerId' => $this->customer]),
                            ]),
                        Tab::make(__('filament/pages/customer_payments.tabs.due_today'))
                            ->icon(Heroicon::OutlinedBellAlert)
                            ->badge(fn (): ?int => (new WeeklyPaymentReminders)->dueOn(today())->count() ?: null)
                            ->badgeColor('warning')
                            ->schema([
                                Livewire::make(WeeklyPaymentRemindersTable::class, ['late' => false])->key('reminders-due-today'),
                            ]),
                        Tab::make(__('filament/pages/customer_payments.tabs.late'))
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->badge(fn (): ?int => (new WeeklyPaymentReminders)->lateOn(today())->count() ?: null)
                            ->badgeColor('danger')
                            ->schema([
                                Livewire::make(WeeklyPaymentRemindersTable::class, ['late' => true])->key('reminders-late'),
                            ]),
                    ])
                    ->activeTab(fn (): int => $this->customer === null ? 1 : 2),
            ]);
    }
}
