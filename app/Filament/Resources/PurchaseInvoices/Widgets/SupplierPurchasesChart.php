<?php

namespace App\Filament\Resources\PurchaseInvoices\Widgets;

use App\Filament\Widgets\Charts\SupplierPieChart;
use App\Models\Invoice;

/**
 * A pie of the suppliers we buy from the most, by the amounts of their purchase invoices, from Invoice::purchasesBySupplier().
 */
class SupplierPurchasesChart extends SupplierPieChart
{
    protected function heading(): string
    {
        return __('filament/resources/purchase_invoice.widgets.purchases_chart.heading');
    }

    protected function parts(): array
    {
        $purchases = Invoice::purchasesBySupplier();

        return [
            'suppliers' => array_map(fn (array $supplier): array => ['name' => $supplier['name'], 'value' => $supplier['amount']], $purchases['suppliers']),
            'others' => $purchases['others'],
        ];
    }
}
