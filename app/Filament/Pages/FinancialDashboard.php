<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Metrics\AccountantBalanceMetric;
use App\Filament\Widgets\Metrics\CashierMoneyTransferMetric;
use App\Filament\Widgets\Metrics\CashSalesBoxMetric;
use App\Filament\Widgets\Metrics\GeneralExpensesMetric;
use App\Filament\Widgets\Metrics\InvoiceCountMetric;
use App\Filament\Widgets\Metrics\PostpaidSalesBoxMetric;
use App\Filament\Widgets\Metrics\PurchaseExpensesMetric;
use App\Filament\Widgets\Metrics\RevenuesMetric;
use App\Filament\Widgets\Metrics\StockValueMetric;
use App\Filament\Widgets\Metrics\TotalDebtMetric;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The financial dashboard: the metric cards of the accounts. It comes right after the home dashboard in the sidebar,
 * and only a user with the financial_dashboard.view permission, or a super user, can open it.
 */
class FinancialDashboard extends Dashboard
{
    protected static string $routePath = 'financial-dashboard';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', static::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/pages/financial_dashboard.title');
    }

    public function getTitle(): string
    {
        return __('filament/pages/financial_dashboard.title');
    }

    /**
     * One column on small screens, two on medium screens, and four on large screens.
     *
     * @return array<string, int>
     */
    public function getColumns(): array
    {
        return ['md' => 2, 'xl' => 4];
    }

    /**
     * The accountant's box and the cashier money transfer card take half the width each; every other card takes one column.
     */
    public function getWidgets(): array
    {
        return [
            AccountantBalanceMetric::make(['columnSpan' => ['md' => 1, 'xl' => 2]]),
            CashierMoneyTransferMetric::make(['columnSpan' => ['md' => 1, 'xl' => 2]]),
            CashSalesBoxMetric::class,
            PostpaidSalesBoxMetric::class,
            GeneralExpensesMetric::class,
            PurchaseExpensesMetric::class,
            RevenuesMetric::class,
            InvoiceCountMetric::class,
            TotalDebtMetric::class,
            StockValueMetric::class,
        ];
    }
}
