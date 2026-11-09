<?php

namespace App\Admin\Pages;

/**
 * The financial dashboard: the metric cards of the accounts and the cashier money transfer. Its permissions are financial_dashboard.view and financial_dashboard.transfer_cashier_money.
 */
class FinancialDashboard extends Page
{
    public static function label(): string
    {
        return __('filament/pages/financial_dashboard.title');
    }
}
