<?php

namespace App\Policies;

use App\Models\User;

/**
 * The financial dashboard is a page that is only viewed, and its cashier money transfer card moves money:
 * the permissions are financial_dashboard.view and financial_dashboard.transfer_cashier_money.
 */
class FinancialDashboardPolicy extends ModelPolicy
{
    /**
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view'];
    }

    /**
     * Whether the user can see the cashier money transfer card and transfer a cashier's money from it.
     */
    public function transferCashierMoney(User $user): bool
    {
        return $this->hasPermission($user, 'transferCashierMoney');
    }
}
