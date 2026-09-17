<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderPaymentType;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * The shape of the sale only. The sale rules themselves (the customer, the prices, and what can be sold)
     * belong to the checkout and are not repeated here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_type' => ['required', Rule::enum(OrderPaymentType::class)],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * The sale as the checkout takes it. The date is today on the server, never from the device.
     */
    public function toCheckoutData(): CheckoutData
    {
        return new CheckoutData(
            customerId: $this->integer('customer_id'),
            paymentType: OrderPaymentType::from($this->string('payment_type')->value()),
            date: now()->toDateString(),
            lines: array_values(array_map(
                fn (array $line): CheckoutLine => new CheckoutLine((int) $line['item_id'], (float) $line['quantity']),
                $this->array('lines'),
            )),
            notes: $this->string('notes')->value() ?: null,
        );
    }
}
