<?php

namespace App\Http\Resources\Admin\Sales;

use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A customer as a row of the customers list and as its page: its details, its contract, and its debt (from Customer::withDebt on a
 * list, so it is read in the list's own query). It can be paid by a user who may record payments while it is not deleted and owes.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $debt = $this->resource->debt();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'payment_type' => SalesPayload::enum($this->payment_type),
            'is_invoice_by_invoice' => (bool) $this->is_invoice_by_invoice,
            'contract' => $this->contract,
            'contract_url' => SalesPayload::fileUrl($this->contract),
            'debt' => $debt,
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => [
                ...SalesPayload::abilities($this->resource, editableWhenDeleted: true),
                'pay' => ! $this->resource->trashed() && $debt > 0 && $this->canRecordPayments($request),
            ],
        ];
    }

    private function canRecordPayments(Request $request): bool
    {
        if (! $request->attributes->has('sales_can_pay')) {
            $request->attributes->set('sales_can_pay', Gate::allows('create', CustomerPayment::class));
        }

        return (bool) $request->attributes->get('sales_can_pay');
    }
}
