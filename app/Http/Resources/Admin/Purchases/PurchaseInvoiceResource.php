<?php

namespace App\Http\Resources\Admin\Purchases;

use App\Enums\InvoiceConfirmation;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Container;
use App\Models\Invoice;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * A purchase invoice. In a list: its name, supplier, category, container, amounts, status, stage, the side it waits for,
 * and the next steps the user may take on it. On its page (with its lines loaded) also its rates, costs, confirmations,
 * lines, images and notes, and the split it belongs to.
 *
 * @mixin Invoice
 */
class PurchaseInvoiceResource extends JsonResource
{
    /**
     * An enum as the browser shows it: its value, its label, and its color when it has one.
     *
     * @return array{value: string, label: string, color: ?string}|null
     */
    public static function enum(?object $case): ?array
    {
        if ($case === null) {
            return null;
        }

        return [
            'value' => $case->value,
            'label' => $case instanceof HasLabel ? (string) $case->getLabel() : (string) $case->value,
            'color' => $case instanceof HasColor ? $case->getColor() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $awaited = $this->awaitedConfirmation();
        $next = $this->stage?->next();
        $user = $request->user();
        $mayUpdate = Gate::allows('update', $this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'split' => $this->splitFact(),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier === null ? null : ['id' => $this->supplier->id, 'name' => $this->supplier->name_ar],
            'invoice_category_id' => $this->invoice_category_id,
            'category' => $this->category === null ? null : ['id' => $this->category->id, 'name' => $this->category->name],
            'container' => $this->container === null ? null : ['id' => $this->container->id, 'name' => $this->container->displayName(), 'can_view' => Gate::allows('viewAny', Container::class)],
            'date' => $this->date?->toDateString(),
            'amount' => (float) $this->amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining' => (float) $this->remaining,
            'status' => self::enum($this->status),
            'stage' => self::enum($this->stage),
            'awaited_confirmation' => self::enum($awaited),
            'user' => $this->relationLoaded('user') ? $this->user?->name : null,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'print_url' => route('purchase-invoices.print', ['invoice' => $this->resource]),
            'label_url' => route('purchase-invoices.label', $this->resource),
            'can' => [
                ...ListsRecords::abilitiesOf($this->resource),
                'advance_stage' => ! $this->trashed() && $next !== null && $mayUpdate,
                'next_stage' => self::enum($next),
                'confirm' => $awaited !== null && Gate::allows($awaited->ability(), $this->resource),
                'revert_confirmation' => $user?->is_super === true && ! $this->trashed() && $awaited !== InvoiceConfirmation::Importer,
            ],
            ...($this->relationLoaded('items') ? $this->details($request) : []),
        ];
    }

    /**
     * What the invoice's page and edit form add to the list's row.
     *
     * @return array<string, mixed>
     */
    private function details(Request $request): array
    {
        $images = array_values(array_filter((array) $this->images));

        return [
            'exchange_rate' => $this->exchange_rate,
            'exchange_rate_source' => self::enum($this->exchange_rate_source),
            'exchange_rate_source_url' => $this->exchange_rate_source?->url(),
            'dinar_rate' => $this->dinar_rate,
            'dinars_per_dollar' => $this->dinarsPerDollar(),
            'cbm_price' => $this->cbm_price,
            'cbm_price_locked' => $this->cbm_price_locked_at !== null,
            'cbm' => (float) $this->cbm,
            'amount_usd' => $this->amount_usd,
            'amount_iqd' => $this->amount_iqd,
            'transport_cost_usd' => $this->transport_cost_usd,
            'transport_cost_iqd' => $this->transport_cost_iqd,
            'total_iqd' => $this->amount_iqd === null ? null : $this->amount_iqd + (float) $this->transport_cost_iqd,
            'importer_checker' => $this->importerChecker?->name,
            'importer_confirmed_at' => $this->importer_confirmed_at?->toIso8601String(),
            'receiver_checker' => $this->receiverChecker?->name,
            'receiver_confirmed_at' => $this->receiver_confirmed_at?->toIso8601String(),
            'split_from' => $this->splitFrom === null ? null : ['id' => $this->splitFrom->id, 'name' => $this->splitFrom->name],
            'images' => array_map(fn (string $path): array => ['path' => $path, 'url' => Storage::disk('public')->url($path)], $images),
            'notes' => $this->notes,
            'items' => PurchaseLineResource::collection($this->items)->resolve($request),
            'has_payoffs' => $this->payoffs()->withTrashed()->exists(),
        ];
    }

    /**
     * The chip that tells a split apart: a part says which invoice it was split from, an original which parts were split off it.
     *
     * @return array{text: string, hint: string, tone: string}|null
     */
    private function splitFact(): ?array
    {
        if ($this->split_from_invoice_id !== null) {
            return [
                'text' => __('filament/resources/purchase_invoice.split.part', ['number' => $this->split_from_invoice_id]),
                'hint' => __('filament/resources/purchase_invoice.split.part_hint', ['number' => $this->split_from_invoice_id]),
                'tone' => 'warning',
            ];
        }

        if ($this->relationLoaded('splitParts') && $this->splitParts->isNotEmpty()) {
            return [
                'text' => __('filament/resources/purchase_invoice.split.original', [
                    'numbers' => $this->splitParts->pluck('id')->map(fn (int $id): string => "#{$id}")->implode(' '),
                ]),
                'hint' => __('filament/resources/purchase_invoice.split.original_hint'),
                'tone' => 'gray',
            ];
        }

        return null;
    }
}
