<?php

namespace App\Metrics\Cards;

use App\Enums\OrderPaymentType;

/**
 * The cash sales box: the cash sales from the orders minus all the cash transferred, of every cashier.
 */
class CashSalesBoxMetric extends SalesBoxMetric
{
    protected function paymentType(): OrderPaymentType
    {
        return OrderPaymentType::Cash;
    }
}
