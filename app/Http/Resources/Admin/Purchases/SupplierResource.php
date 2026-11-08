<?php

namespace App\Http\Resources\Admin\Purchases;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A supplier: its names, phone, email, contract, and notes, with what the user may do with it.
 * Paying it (one amount spread on its invoices) is offered for a supplier that is not deleted, to whoever may add payoffs:
 * the controller checks that once and marks the suppliers with may_pay.
 *
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_zh' => $this->name_zh,
            'phone' => $this->phone,
            'email' => $this->email,
            'contract' => $this->contract,
            'contract_url' => filled($this->contract) ? Storage::disk('public')->url($this->contract) : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => [
                ...ListsRecords::abilitiesOf($this->resource),
                'pay' => ! $this->trashed() && (bool) ($this->may_pay ?? false),
            ],
        ];
    }
}
