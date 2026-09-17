<?php

namespace App\Filament\Resources\Suppliers\Widgets;

use App\Filament\Widgets\Charts\SupplierPieChart;
use App\Models\Invoice;

/**
 * A pie of the debt to each supplier, from Invoice::debtBySupplier().
 */
class SupplierDebtsChart extends SupplierPieChart
{
    protected function heading(): string
    {
        return __('filament/resources/supplier.widgets.debts_chart.heading');
    }

    protected function parts(): array
    {
        $debts = Invoice::debtBySupplier();

        return [
            'suppliers' => array_map(fn (array $supplier): array => ['name' => $supplier['name'], 'value' => $supplier['debt']], $debts['suppliers']),
            'others' => $debts['others'],
        ];
    }
}
