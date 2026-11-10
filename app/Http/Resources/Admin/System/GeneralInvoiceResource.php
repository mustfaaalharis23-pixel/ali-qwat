<?php

namespace App\Http\Resources\Admin\System;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A general invoice. A money transfer invoice is never edited from the screens; it is deleted like any invoice (within a day of
 * creating it), except one made by a customer payment, which the invoice itself refuses.
 * Only a super user restores a deleted general invoice. Its lines, images, and notes come only on its own page.
 *
 * @mixin Invoice
 */
class GeneralInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => EnumValue::of($this->type),
            'date' => $this->date?->toDateString(),
            'amount' => (float) $this->amount,
            'general_description' => $this->generalDescription === null ? null : [
                'id' => $this->generalDescription->id,
                'name' => $this->generalDescription->name,
            ],
            'cashier' => $this->cashier === null ? null : ['id' => $this->cashier->id, 'name' => $this->cashier->name],
            'user' => $this->user === null ? null : ['id' => $this->user->id, 'name' => $this->user->name],
            'is_money_transfer' => $this->resource->isMoneyTransfer(),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn (InvoiceItem $line): array => [
                'id' => $line->id,
                'specific_description_id' => $line->specific_description_id,
                'specific_description' => $line->specificDescription?->name,
                'price' => (float) $line->price,
            ])->values()->all()),
            'images' => $this->when($this->relationLoaded('items'), fn (): array => array_map(fn (string $path): array => [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ], array_values(array_filter((array) $this->images, 'is_string')))),
            'notes' => $this->when($this->relationLoaded('items'), fn (): ?string => $this->notes),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => self::abilities($this->resource),
        ];
    }

    /**
     * @return array{view: bool, update: bool, delete: bool, restore: bool, force_delete: bool}
     */
    public static function abilities(Invoice $invoice): array
    {
        $can = ListsRecords::abilitiesOf($invoice);

        if ($invoice->isMoneyTransfer()) {
            $can['update'] = false;
        }

        $can['restore'] = $can['restore'] && request()->user()?->is_super === true;

        return $can;
    }
}
