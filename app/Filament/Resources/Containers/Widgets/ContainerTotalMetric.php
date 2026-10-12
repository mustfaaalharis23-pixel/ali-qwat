<?php

namespace App\Filament\Resources\Containers\Widgets;

use App\Filament\Widgets\Metrics\ValueMetric;
use App\Metrics\Currency;
use App\Metrics\MetricRange;
use App\Models\Container;
use Livewire\Attributes\On;

/**
 * The template of the total cards on a container's page: the container comes from the page,
 * the numbers are overall totals so there is no range list, and amounts are in US dollars.
 * The cards show their numbers again when an invoice is added to the container or taken out of it.
 */
abstract class ContainerTotalMetric extends ValueMetric
{
    /**
     * The container of the page, passed in by the page.
     */
    public ?Container $record = null;

    /**
     * Renders the card again, so its number is read again from the database.
     */
    #[On('container-invoices-changed')]
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
        return Currency::UsDollar;
    }
}
