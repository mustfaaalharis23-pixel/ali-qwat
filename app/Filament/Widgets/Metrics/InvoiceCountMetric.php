<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\InvoiceCount;
use App\Metrics\MetricRange;

/**
 * The number of invoices, with every range to choose from. It opens on all time.
 */
class InvoiceCountMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.invoice_count');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.invoice_count.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.invoice_count.example');
    }

    protected function calculation(): Calculation
    {
        return new InvoiceCount;
    }

    protected function ranges(): array
    {
        return MetricRange::cases();
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }
}
