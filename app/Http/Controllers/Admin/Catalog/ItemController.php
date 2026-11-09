<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Catalog\ItemResource;
use App\Metrics\Cards\ActiveItemCountMetric;
use App\Metrics\Cards\InactiveItemCountMetric;
use App\Metrics\Cards\ItemCountMetric;
use App\Metrics\Cards\Items\AvailableQuantityMetric;
use App\Metrics\Cards\Items\AveragePurchasePriceMetric;
use App\Metrics\Cards\Items\HighestPurchasePriceMetric;
use App\Metrics\Cards\Items\IncomingQuantityMetric;
use App\Metrics\Cards\Items\ItemMetric;
use App\Metrics\Cards\Items\LastPurchasePriceMetric;
use App\Metrics\Cards\Items\LowestPurchasePriceMetric;
use App\Metrics\Cards\Items\ProfitMarginMetric;
use App\Metrics\Cards\Items\SoldQuantityMetric;
use App\Metrics\Cards\ItemWithoutSupplierCountMetric;
use App\Metrics\Cards\ValueMetric;
use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The items, as the panel's item screens had them: a list of the active items by default (the status filter reaches the
 * inactive ones on purpose), a form with the image on the public disk, the item's page with its cards and its quantities
 * by power and by color, and the actions of the lenses (fix the price, activate, work the stock out again, set the supplier).
 * The cost price and everything worked out from it show only to a user with item.view_cost_price.
 */
class ItemController extends Controller
{
    use ListsRecords;

    /**
     * The cards above the items list.
     *
     * @var list<class-string<ValueMetric>>
     */
    private const LIST_METRICS = [
        ItemCountMetric::class,
        ActiveItemCountMetric::class,
        InactiveItemCountMetric::class,
        ItemWithoutSupplierCountMetric::class,
    ];

    /**
     * The cards above an item's page. The purchase price cards and the profit margin decide themselves (canView) who sees them.
     *
     * @var list<class-string<ItemMetric>>
     */
    private const ITEM_METRICS = [
        AvailableQuantityMetric::class,
        IncomingQuantityMetric::class,
        SoldQuantityMetric::class,
        LastPurchasePriceMetric::class,
        AveragePurchasePriceMetric::class,
        HighestPurchasePriceMetric::class,
        LowestPurchasePriceMetric::class,
        ProfitMarginMetric::class,
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Item::class);

        $canSeeTheCost = ItemResource::canSeeTheCost($request);
        $status = (string) $request->input('filter.status', 'active');

        // The supplier filter is a search select, so it may also come as ?supplier_id=.

        $items = $this->listRecords(
            Item::query()
                ->with('supplier:id,name_ar')
                ->when($status === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
                ->when($status === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
                ->when($request->integer('supplier_id'), fn (Builder $query, int $supplierId): Builder => $query->where('supplier_id', $supplierId)),
            $request,
            searchable: ['name'],
            sortable: [
                'id' => 'id',
                'name' => 'name',
                'available_quantity' => 'available_quantity',
                'incoming_quantity' => 'incoming_quantity',
                'sale_price' => 'sale_price',
                ...($canSeeTheCost ? ['cost_price' => 'cost_price'] : []),
            ],
            filters: [
                'supplier_id' => fn (Builder $query, string $value): Builder => $query->where('supplier_id', (int) $value),
            ],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        return ItemResource::collection($items)->additional(['can' => $this->listAbilities($request)]);
    }

    /**
     * What the user may do on the items list: create, edit, delete, restore, and delete forever the items, and see their cost.
     *
     * @return array<string, bool>
     */
    private function listAbilities(Request $request): array
    {
        return [
            'create' => Gate::allows('create', Item::class),
            'update' => Gate::allows('update', new Item),
            'delete' => Gate::allows('deleteAny', Item::class),
            'restore' => Gate::allows('restoreAny', Item::class),
            'force_delete' => Gate::allows('forceDeleteAny', Item::class),
            'view_cost_price' => ItemResource::canSeeTheCost($request),
        ];
    }

    /**
     * The item count cards above the list, with what the user may do on the list, so the page knows its columns and its bulk actions.
     */
    public function listMetrics(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Item::class);

        return response()->json([
            'data' => array_map(fn (string $metric): array => $this->payloadOf($metric), self::LIST_METRICS),
            'can' => $this->listAbilities($request),
        ]);
    }

    public function store(Request $request): ItemResource
    {
        Gate::authorize('create', Item::class);

        $item = Item::query()->create($this->validated($request));

        return new ItemResource($item->load('supplier:id,name_ar'));
    }

    public function show(Request $request, int $item): JsonResponse
    {
        $record = Item::withTrashed()->with('supplier:id,name_ar')->findOrFail($item);
        Gate::authorize('view', $record);

        return (new ItemResource($record))->withQuantities()->additional([
            'can' => ['view_cost_price' => ItemResource::canSeeTheCost($request)],
        ])->response();
    }

    /**
     * The cards above the item's page, the ones the user may see.
     */
    public function metrics(int $item): JsonResponse
    {
        $record = Item::withTrashed()->findOrFail($item);
        Gate::authorize('view', $record);

        $cards = collect(self::ITEM_METRICS)
            ->filter(fn (string $metric): bool => $metric::canView())
            ->map(fn (string $metric): array => $this->payloadOf($metric, $record))
            ->values();

        return response()->json(['data' => $cards]);
    }

    public function update(Request $request, Item $item): ItemResource
    {
        Gate::authorize('update', $item);

        $item->update($this->validated($request, $item));

        return new ItemResource($item->load('supplier:id,name_ar'));
    }

    public function destroy(Item $item): JsonResponse
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return response()->json(status: 204);
    }

    public function restore(int $item): ItemResource
    {
        $record = Item::onlyTrashed()->findOrFail($item);
        Gate::authorize('restore', $record);

        $record->restore();

        return new ItemResource($record);
    }

    public function forceDelete(int $item): JsonResponse
    {
        $record = Item::withTrashed()->findOrFail($item);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * Deletes, restores, or deletes forever the selected items. Every item is checked on its own before any is touched.
     */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['delete', 'restore', 'force_delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ability = Str::camel($data['action']);
        $items = Item::withTrashed()->whereKey($data['ids'])->get();

        $items->each(fn (Item $item) => Gate::authorize($ability, $item));

        $items
            ->filter(fn (Item $item): bool => match ($data['action']) {
                'delete' => ! $item->trashed(),
                'restore' => $item->trashed(),
                default => true,
            })
            ->each(fn (Item $item) => match ($data['action']) {
                'delete' => $item->delete(),
                'restore' => $item->restore(),
                default => $item->forceDelete(),
            });

        return response()->json(['count' => $items->count()]);
    }

    /**
     * Sets the sale price of the item. The page works it out from the margin or the percentage the user writes;
     * only the sale price is saved, as the panel's "Fix the price" action did.
     */
    public function salePrice(Request $request, Item $item): ItemResource
    {
        Gate::authorize('update', $item);

        $this->stripThousandsSeparators($request, 'sale_price');

        $data = $request->validate(
            ['sale_price' => ['required', 'numeric', 'min:0']],
            attributes: ['sale_price' => __('filament/resources/item.fields.sale_price')],
        );

        $item->update(['sale_price' => (float) $data['sale_price']]);

        return new ItemResource($item->load('supplier:id,name_ar'));
    }

    /**
     * Activates an inactive item, so its quantity can be sold again.
     */
    public function activate(Item $item): ItemResource
    {
        Gate::authorize('update', $item);

        $item->update(['is_active' => true]);

        return new ItemResource($item->load('supplier:id,name_ar'));
    }

    /**
     * Works the item's quantities and cost out again from the purchases and the orders themselves.
     */
    public function recalculateStock(Item $item): ItemResource
    {
        Gate::authorize('update', $item);

        $item->recalculateStock();

        return new ItemResource($item->refresh()->load('supplier:id,name_ar'));
    }

    /**
     * Sets the supplier of one item.
     */
    public function supplier(Request $request, Item $item): ItemResource
    {
        Gate::authorize('update', $item);

        $item->update(['supplier_id' => $this->validatedSupplier($request)]);

        return new ItemResource($item->load('supplier:id,name_ar'));
    }

    /**
     * Sets one supplier on all the selected items. Each item is saved on its own, so every change is audited.
     */
    public function bulkSupplier(Request $request): JsonResponse
    {
        $supplierId = $this->validatedSupplier($request);
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];

        /** @var Collection<int, Item> $items */
        $items = Item::query()->whereKey($ids)->get();
        $items->each(fn (Item $item) => Gate::authorize('update', $item));
        $items->each(fn (Item $item): bool => $item->update(['supplier_id' => $supplierId]));

        return response()->json(['count' => $items->count()]);
    }

    /**
     * One card as the browser shows it, with the item of the page when it is an item card.
     *
     * @param  class-string<ValueMetric>  $metric
     * @return array<string, mixed>
     */
    private function payloadOf(string $metric, ?Item $item = null): array
    {
        $card = app($metric);

        if ($card instanceof ItemMetric) {
            $card->record = $item;
        }

        return ['key' => Str::snake(Str::beforeLast(class_basename($metric), 'Metric')), ...$card->toPayload()];
    }

    private function validatedSupplier(Request $request): int
    {
        return (int) $request->validate(
            ['supplier_id' => ['required', 'integer', Rule::exists(Supplier::class, 'id')->withoutTrashed()]],
            attributes: ['supplier_id' => __('filament/resources/item.fields.supplier_id')],
        )['supplier_id'];
    }

    /**
     * The fields of the item form. The image is required on a new item; an item being edited keeps its image unless
     * a new one is sent. A sale price left empty is saved as 0, to be set later.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Item $item = null): array
    {
        $this->stripThousandsSeparators($request, 'sale_price');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', Rule::exists(Supplier::class, 'id')],
            'image' => [$item === null ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'pieces_per_packet' => ['required', 'integer', Rule::in(Item::PACKET_SIZES)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
        ], attributes: collect(['name', 'supplier_id', 'image', 'pieces_per_packet', 'description', 'is_active', 'sale_price'])
            ->mapWithKeys(fn (string $field): array => [$field => __("filament/resources/item.fields.{$field}")])
            ->all());

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('items', 'public');
        } else {
            unset($data['image']);
        }

        $data['sale_price'] = (float) ($data['sale_price'] ?? 0);

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        return $data;
    }

    /**
     * A money field may come with its thousands separators, which are not part of the number.
     */
    private function stripThousandsSeparators(Request $request, string $field): void
    {
        if (is_string($request->input($field))) {
            $request->merge([$field => str_replace(',', '', $request->input($field))]);
        }
    }
}
