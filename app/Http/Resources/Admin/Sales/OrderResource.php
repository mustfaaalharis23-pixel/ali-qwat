<?php

namespace App\Http\Resources\Admin\Sales;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as a row of the orders list and of the invoice printing list. What it cost us and its profit (and its margin) are sent
 * only to a user who may see the cost. The print links are added by the list that shows them.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seesCost = SalesPayload::seesCost();
        $totalAmount = (float) $this->total_amount;

        return [
            'id' => $this->id,
            'customer' => $this->customer === null ? null : ['id' => $this->customer->id, 'name' => $this->customer->name],
            'date' => $this->date?->toDateString(),
            'payment_type' => SalesPayload::enum($this->payment_type),
            'items_count' => $this->whenCounted('items'),
            'amount' => (float) $this->amount,
            'total_amount' => $totalAmount,
            'paid_amount' => (float) $this->paid_amount,
            'down_payment' => $this->down_payment === null ? null : (float) $this->down_payment,
            'weekly_payment' => $this->weekly_payment === null ? null : (float) $this->weekly_payment,
            'remaining' => (float) $this->remaining,
            'total_cost' => $seesCost ? (float) $this->total_cost : null,
            'profit' => $seesCost ? (float) $this->profit : null,
            'profit_margin_percentage' => $seesCost ? ($totalAmount <= 0 ? 0.0 : round((float) $this->profit / $totalAmount * 100, 1)) : null,
            'cashier' => $this->cashier?->name,
            'user' => $this->user?->name,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'print' => $this->when($request->attributes->get('with_print_links', false), fn (): ?array => $this->printLinks()),
            'can' => SalesPayload::abilities($this->resource),
        ];
    }

    /**
     * The order's invoice to print, as a full page and as the small printer's receipt. A deleted order has no invoice.
     *
     * @return array{a4: string, pos: string}|null
     */
    public function printLinks(): ?array
    {
        if ($this->resource->trashed()) {
            return null;
        }

        return [
            'a4' => route('invoices.print', ['order' => $this->resource, 'format' => 'a4']),
            'pos' => route('invoices.print', ['order' => $this->resource, 'format' => 'pos']),
        ];
    }
}
