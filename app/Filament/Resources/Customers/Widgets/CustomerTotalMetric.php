<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Filament\Widgets\Metrics\ValueMetric;
use App\Metrics\Currency;
use App\Metrics\MetricRange;
use App\Models\Customer;
use Livewire\Attributes\On;

/**
 * The template of the total cards on a customer's page: the customer comes from the page,
 * the numbers are overall totals so there is no range list, and amounts are in Iraqi dinar.
 * The cards show their numbers again when a payment of the customer is recorded or deleted.
 */
abstract class CustomerTotalMetric extends ValueMetric
{
    /**
     * The customer of the page, passed in by the page.
     */
    public ?Customer $record = null;

    /**
     * Renders the card again, so its number is read again from the database.
     */
    #[On('customer-payments-changed')]
    public function refreshTotals(): void {}

    protected function ranges(): array
    {
        return [MetricRange::All];
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }

    protected function currency(): ?Currency
    {
        return Currency::IraqiDinar;
    }
}
