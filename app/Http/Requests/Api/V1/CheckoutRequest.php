<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderPaymentType;
use App\Enums\Package;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'down_payment' => ['nullable', 'numeric'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', $this->wholePackages()],
            'lines.*.package' => ['nullable', Rule::enum(Package::class)],
        ];
    }

    /**
     * The quantity is in pieces, and it makes whole packages of the line's package: 24 with a dozen, never 25.
     * A line without a package is a piece. A wrong package or quantity is rejected by its own rule, so it is not checked here.
     */
    private function wholePackages(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $package = $this->input(Str::replaceLast('.quantity', '.package', $attribute));
            $package = $package === null ? Package::Piece : Package::tryFrom((string) $package);

            if ($package === null || ! is_numeric($value) || $package->holdsWhole((float) $value)) {
                return;
            }

            $fail(__('orders.validation.quantity_not_whole_packages', [
                'package' => $package->getLabel(),
                'size' => $package->size(),
            ]));
        };
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
                fn (array $line): CheckoutLine => new CheckoutLine(
                    (int) $line['item_id'],
                    (float) $line['quantity'],
                    Package::tryFrom((string) ($line['package'] ?? '')) ?? Package::Piece,
                ),
                $this->array('lines'),
            )),
            notes: $this->string('notes')->value() ?: null,
            downPayment: $this->filled('down_payment') ? $this->float('down_payment') : null,
        );
    }
}
