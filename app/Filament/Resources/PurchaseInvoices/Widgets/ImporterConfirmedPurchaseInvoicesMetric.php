<?php

namespace App\Filament\Resources\PurchaseInvoices\Widgets;

use App\Enums\InvoiceConfirmation;
use App\Filament\Widgets\Metrics\ValueMetric;
use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\MetricRange;
use App\Models\Invoice;

/**
 * The number of purchase invoices the importer confirmed and the receiver did not: what is bought and on its way.
 * It is the count now, so there is no range list.
 */
class ImporterConfirmedPurchaseInvoicesMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('filament/resources/purchase_invoice.widgets.importer_confirmed_count');
    }

    protected function explanation(): string
    {
        return __('filament/resources/purchase_invoice.widgets.importer_confirmed_count_info.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/purchase_invoice.widgets.importer_confirmed_count_info.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): int => Invoice::purchasesAwaitingCount(InvoiceConfirmation::Receiver));
    }

    protected function ranges(): array
    {
        return [MetricRange::All];
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }
}
