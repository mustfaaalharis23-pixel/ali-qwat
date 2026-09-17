<?php

namespace App\Filament\Widgets\Metrics;

use App\Enums\OrderPaymentType;

/**
 * The postpaid sales box: the postpaid sales from the orders minus all the postpaid money transferred.
 */
class PostpaidSalesBoxMetric extends SalesBoxMetric
{
    protected function paymentType(): OrderPaymentType
    {
        return OrderPaymentType::Postpaid;
    }
}
