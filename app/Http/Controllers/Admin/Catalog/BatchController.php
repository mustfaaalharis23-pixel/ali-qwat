<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Catalog\BatchResource;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The stock batches, read only: a batch is written with its purchase line, never by hand. The same list is the batches tab
 * of an item's page (?item_id=). The item and the power also come as ?item_id= and ?lens_power_id=, from the search selects. It is filtered by the status of the batch's invoice and by power, newest first.
 */
class BatchController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Batch::class);

        $batches = $this->listRecords(
            Batch::query()
                ->with(['item:id,name', 'invoice:id,date,status', 'lensPower:id,value', 'colors.color:id,name'])
                ->when($request->integer('item_id'), fn (Builder $query, int $itemId): Builder => $query->where('item_id', $itemId))
                ->when($request->integer('lens_power_id'), fn (Builder $query, int $powerId): Builder => $query->where('lens_power_id', $powerId)),
            $request,
            searchable: [
                'id',
                'invoice_id',
                fn (Builder $query, string $search): Builder => $query->orWhereHas('item', fn (Builder $query): Builder => $query->whereLike('name', "%{$search}%", caseSensitive: false)),
            ],
            sortable: ['id' => 'id', 'invoice_id' => 'invoice_id', 'quantity' => 'quantity', 'purchase_price' => 'purchase_price'],
            filters: [
                'item_id' => fn (Builder $query, string $value): Builder => $query->where('item_id', (int) $value),
                'status' => fn (Builder $query, string $value): Builder => $query->whereHas('invoice', fn (Builder $query): Builder => $query->where('status', InvoiceStatus::tryFrom($value)?->value ?? '')),
                'lens_power_id' => fn (Builder $query, string $value): Builder => $query->where('lens_power_id', (int) $value),
            ],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        return BatchResource::collection($batches)->additional([
            'statuses' => array_map(fn (InvoiceStatus $status): array => ['value' => $status->value, 'label' => $status->getLabel()], InvoiceStatus::cases()),
        ]);
    }
}
