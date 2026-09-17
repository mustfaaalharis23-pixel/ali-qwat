<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Pages\CustomerPayments;
use App\Filament\Resources\Customers\Actions\PayDebtAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Widgets\CustomerAverageOrderMetric;
use App\Filament\Resources\Customers\Widgets\CustomerDebtMetric;
use App\Filament\Resources\Customers\Widgets\CustomerItemsCountMetric;
use App\Filament\Resources\Customers\Widgets\CustomerLastOrderMetric;
use App\Filament\Resources\Customers\Widgets\CustomerLastPaymentMetric;
use App\Filament\Resources\Customers\Widgets\CustomerOrdersCountMetric;
use App\Filament\Resources\Customers\Widgets\CustomerPaidMetric;
use App\Filament\Resources\Customers\Widgets\CustomerSalesMetric;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PayDebtAction::make(),
            Action::make('statement')
                ->label(__('filament/resources/customer.actions.statement'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(fn (): string => CustomerPayments::getUrl(['customer' => $this->getRecord()->getKey()])),
            EditAction::make(),
        ];
    }

    /**
     * The customer's totals above its details: first its account (debt, paid, last payment), then its buying
     * (sales, orders, average order, items, last order).
     */
    protected function getHeaderWidgets(): array
    {
        return [
            CustomerDebtMetric::class,
            CustomerPaidMetric::class,
            CustomerLastPaymentMetric::class,
            CustomerSalesMetric::class,
            CustomerOrdersCountMetric::class,
            CustomerAverageOrderMetric::class,
            CustomerItemsCountMetric::class,
            CustomerLastOrderMetric::class,
        ];
    }

    /**
     * One card per row on small screens, two on medium screens, and four on large screens, so the eight cards fill two rows.
     *
     * @return array<string, int>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
