<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderPaymentType;
use App\Models\Item;
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
     * belong to the checkout and are not repeated here. A line is whole packets, at a lens power and in the colors
     * of one packet when the item has them.
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
            'lines.*.packets' => ['required', 'integer', 'gt:0', $this->itemWithAPacket()],
            'lines.*.lens_power_id' => ['nullable', 'integer', 'exists:lens_powers,id'],
            'lines.*.colors' => ['nullable', 'array'],
            'lines.*.colors.*.color_id' => ['required', 'integer', 'exists:colors,id'],
            'lines.*.colors.*.pieces' => ['required', 'integer', 'gt:0'],
        ];
    }

    /**
     * An item is sold by the whole packet only, so an item without a packet size cannot be sold.
     */
    private function itemWithAPacket(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $item = $this->itemOf(Str::beforeLast($attribute, '.packets'));

            if ($item === null || $item->pieces_per_packet !== null) {
                return;
            }

            $fail(__('orders.validation.item_has_no_packet', ['item' => $item->name]));
        };
    }

    /**
     * The item of the line at the given path, or null when it is not an item.
     */
    private function itemOf(string $line): ?Item
    {
        $itemId = $this->input("{$line}.item_id");

        return is_numeric($itemId) ? Item::query()->find((int) $itemId) : null;
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
                    lensPowerId: isset($line['lens_power_id']) ? (int) $line['lens_power_id'] : null,
                    packets: (int) $line['packets'],
                    colors: array_map(fn (array $color): array => [
                        'color_id' => (int) $color['color_id'],
                        'pieces' => (float) $color['pieces'],
                    ], $line['colors'] ?? []),
                ),
                $this->array('lines'),
            )),
            notes: $this->string('notes')->value() ?: null,
            downPayment: $this->filled('down_payment') ? $this->float('down_payment') : null,
        );
    }
}
