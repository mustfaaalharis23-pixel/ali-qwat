<?php

namespace App\Orders\Checkout;

use App\Enums\OrderPaymentType;
use Carbon\CarbonInterface;

/**
 * The data of one sale as it comes in: the customer, the payment type, the date, the notes, the sold lines,
 * and the down payment the customer paid with a postpaid sale, or null when there is none.
 * The customer and the payment type can be missing here, so the checkout rejects them with a clear message.
 */
final readonly class CheckoutData
{
    /**
     * @param  list<CheckoutLine>  $lines
     */
    public function __construct(
        public ?int $customerId,
        public ?OrderPaymentType $paymentType,
        public CarbonInterface|string $date,
        public array $lines,
        public ?string $notes = null,
        public ?float $downPayment = null,
    ) {}
}
