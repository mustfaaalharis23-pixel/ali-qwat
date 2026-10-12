<?php

namespace App\Metrics;

use App\Enums\OrderPaymentType;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Order;
use Carbon\CarbonImmutable;

/**
 * What the company was worth at a moment: what it owned minus what it owed.
 *
 *   value = the stock at cost + the purchases waiting for a confirmation − what is owed to the suppliers
 *           + the accountant's box + the cash sales box + the postpaid sales box
 *
 * Every part of it is worked out again from the invoices, the payoffs, and the orders up to that moment,
 * so an old month can be asked about at any time and nothing has to be written down every day.
 * The goods of a purchase invoice both sides did not confirm are not in the stock yet, and the company already owes them,
 * so they are held on their own line at what they were bought for.
 * The postpaid sales box is the money the customers still owe, which the company owns as well.
 */
readonly class CompanyValue
{
    public function __construct(
        public CarbonImmutable $moment,
        public float $stockValue,
        public float $unconfirmedPurchases,
        public float $supplierBalance,
        public float $accountantBox,
        public float $cashSalesBox,
        public float $postpaidSalesBox,
    ) {}

    /**
     * The value of the company at that moment, with every part of it.
     */
    public static function at(CarbonImmutable $moment): self
    {
        $upToTheMoment = new Period(null, $moment);

        return new self(
            moment: $moment,
            stockValue: Item::stockValueAt($moment),
            unconfirmedPurchases: Invoice::unconfirmedPurchasesAt($moment),
            supplierBalance: Invoice::supplierBalanceAt($moment),
            accountantBox: Invoice::accountantBalance($upToTheMoment),
            cashSalesBox: Order::boxBalance(OrderPaymentType::Cash, $upToTheMoment),
            postpaidSalesBox: Order::boxBalance(OrderPaymentType::Postpaid, $upToTheMoment),
        );
    }

    /**
     * The one number: everything the company owned at that moment minus everything it owed.
     */
    public function total(): float
    {
        return $this->stockValue
            + $this->unconfirmedPurchases
            - $this->supplierBalance
            + $this->accountantBox
            + $this->cashSalesBox
            + $this->postpaidSalesBox;
    }
}
