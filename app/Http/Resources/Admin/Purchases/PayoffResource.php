<?php

namespace App\Http\Resources\Admin\Purchases;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Payoff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A payoff of a purchase invoice. The initial payment is never changed or deleted on its own, so its row offers neither.
 *
 * @mixin Payoff
 */
class PayoffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $abilities = ListsRecords::abilitiesOf($this->resource);

        if ($this->is_initial_payment) {
            $abilities = [...$abilities, 'update' => false, 'delete' => false, 'force_delete' => false];
        }

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount,
            'date' => $this->date?->toDateString(),
            'image' => $this->image,
            'image_url' => filled($this->image) ? Storage::disk('public')->url($this->image) : null,
            'is_initial_payment' => (bool) $this->is_initial_payment,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => $abilities,
        ];
    }
}
