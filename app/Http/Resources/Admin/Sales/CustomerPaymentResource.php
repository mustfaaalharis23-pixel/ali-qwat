<?php

namespace App\Http\Resources\Admin\Sales;

use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer payment as a row of the payments lists and as its details: who paid how much and when, its receipt image, who recorded
 * it, its receipt to print (a deleted payment has none), and, once loaded, its withdrawal invoice and the orders it paid.
 *
 * @mixin CustomerPayment
 */
class CustomerPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer === null ? null : ['id' => $this->customer->id, 'name' => $this->customer->name]),
            'date' => $this->date?->toDateString(),
            'amount' => (float) $this->amount,
            'is_down_payment' => (bool) $this->is_down_payment,
            'image_url' => SalesPayload::fileUrl($this->image),
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
            'invoice' => $this->whenLoaded('invoice', fn (): ?array => $this->invoice === null ? null : ['id' => $this->invoice->id, 'name' => $this->invoice->name]),
            'allocations' => $this->whenLoaded('allocations', fn (): array => $this->allocations->map(fn (CustomerPaymentOrder $allocation): array => [
                'order_id' => $allocation->order_id,
                'order_date' => $allocation->order?->date?->toDateString(),
                'order_total' => $allocation->order === null ? null : (float) $allocation->order->total_amount,
                'amount' => (float) $allocation->amount,
            ])->values()->all()),
            'receipt_url' => $this->resource->trashed() ? null : route('customer-payments.receipt', $this->resource),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => SalesPayload::abilities($this->resource),
        ];
    }
}
