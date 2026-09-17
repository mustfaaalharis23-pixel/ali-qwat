<?php

namespace App\Filament\Resources\Customers\Widgets;

use Carbon\CarbonInterface;

/**
 * The days since the customer's last payment, from Customer::daysSinceLastPayment(), with the date of that payment beside them.
 */
class CustomerLastPaymentMetric extends CustomerDaysSinceMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.last_payment');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.last_payment.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.last_payment.example');
    }

    protected function lastDate(): ?CarbonInterface
    {
        return $this->record->lastPaymentDate();
    }

    protected function daysSince(): ?int
    {
        return $this->record->daysSinceLastPayment();
    }
}
