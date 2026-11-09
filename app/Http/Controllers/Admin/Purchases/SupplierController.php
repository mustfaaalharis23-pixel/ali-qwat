<?php

namespace App\Http\Controllers\Admin\Purchases;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Admin\Purchases\Concerns\StoresUploads;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Purchases\PurchaseInvoiceResource;
use App\Http\Resources\Admin\Purchases\SupplierResource;
use App\Invoices\DistributeSupplierPayoff;
use App\Metrics\Cards\Suppliers\SupplierDebtMetric;
use App\Metrics\Cards\Suppliers\SupplierItemsCountMetric;
use App\Metrics\Cards\Suppliers\SupplierPaidMetric;
use App\Metrics\Cards\Suppliers\SupplierPurchasesMetric;
use App\Metrics\Cards\Suppliers\SupplierTotalMetric;
use App\Metrics\Cards\TotalDebtMetric;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\Supplier;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The suppliers: their names, phone, email, contract, and notes. The list carries the total debt card and the pie of the debt to each
 * supplier; a supplier's page its four totals, its items, and its invoices. A supplier is paid from the list: one amount spread on its
 * invoices from the oldest (DistributeSupplierPayoff).
 */
class SupplierController extends Controller
{
    use ListsRecords;
    use StoresUploads;

    /**
     * The total cards of a supplier's page, by their key.
     *
     * @var array<string, class-string<SupplierTotalMetric>>
     */
    private const METRICS = [
        'debt' => SupplierDebtMetric::class,
        'paid' => SupplierPaidMetric::class,
        'purchases' => SupplierPurchasesMetric::class,
        'items_count' => SupplierItemsCountMetric::class,
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Supplier::class);

        $suppliers = $this->listRecords(
            Supplier::query(),
            $request,
            searchable: ['id', 'name_ar', 'name_zh', 'email'],
            sortable: ['id' => 'id', 'name_ar' => 'name_ar', 'name_zh' => 'name_zh'],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        $mayPay = Gate::allows('create', Payoff::class);
        $suppliers->getCollection()->each(fn (Supplier $supplier) => $supplier->setAttribute('may_pay', $mayPay));

        return SupplierResource::collection($suppliers)->additional([
            'can' => ['create' => Gate::allows('create', Supplier::class), 'pay' => Gate::allows('create', Payoff::class)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Supplier::class);

        return (new SupplierResource(Supplier::query()->create($this->validated($request))))->response()->setStatusCode(201);
    }

    public function show(int $supplier): SupplierResource
    {
        $record = Supplier::withTrashed()->findOrFail($supplier);
        Gate::authorize('view', $record);

        return (new SupplierResource($record))->additional(['can' => [
            'pay' => ! $record->trashed() && Gate::allows('create', Payoff::class),
            'view_invoices' => Gate::allows('viewAny', Invoice::class),
            'view_items' => Gate::allows('viewAny', Item::class),
        ]]);
    }

    public function update(Request $request, Supplier $supplier): SupplierResource
    {
        Gate::authorize('update', $supplier);

        $supplier->update($this->validated($request));

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        Gate::authorize('delete', $supplier);

        $supplier->delete();

        return response()->json(status: 204);
    }

    public function restore(int $supplier): SupplierResource
    {
        $record = Supplier::onlyTrashed()->findOrFail($supplier);
        Gate::authorize('restore', $record);

        $record->restore();

        return new SupplierResource($record);
    }

    public function forceDelete(int $supplier): JsonResponse
    {
        $record = Supplier::withTrashed()->findOrFail($supplier);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * The total debt card above the list.
     */
    public function totalDebt(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $card = app(TotalDebtMetric::class);

        return response()->json(['key' => 'total_debt', ...$card->toPayload($request->query('range'))]);
    }

    /**
     * The pie of the debt to each supplier, the rest as others.
     */
    public function chart(): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $debts = Invoice::debtBySupplier();

        return response()->json(PurchaseInvoiceController::pie(
            __('filament/resources/supplier.widgets.debts_chart.heading'),
            array_map(fn (array $supplier): array => ['name' => (string) $supplier['name'], 'value' => (float) $supplier['debt']], $debts['suppliers']),
            (float) $debts['others'],
        ));
    }

    /**
     * One of the totals of a supplier's page.
     */
    public function metric(Request $request, int $supplier, string $key): JsonResponse
    {
        $record = Supplier::withTrashed()->findOrFail($supplier);
        Gate::authorize('view', $record);
        abort_unless(array_key_exists($key, self::METRICS), 404);

        $card = app(self::METRICS[$key]);
        $card->record = $record;

        return response()->json(['key' => $key, ...$card->toPayload($request->query('range'))]);
    }

    /**
     * The supplier's invoices; a row opens the purchase invoice.
     */
    public function invoices(Request $request, int $supplier): AnonymousResourceCollection
    {
        $record = Supplier::withTrashed()->findOrFail($supplier);
        Gate::authorize('view', $record);
        Gate::authorize('viewAny', Invoice::class);

        $invoices = $this->listRecords(
            $record->invoices()->getQuery()->with(['generalDescription' => fn ($query) => $query->withTrashed(), 'supplier' => fn ($query) => $query->withTrashed(), 'container', 'category' => fn ($query) => $query->withTrashed(), 'splitParts:id,split_from_invoice_id']),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'date' => 'date', 'amount' => 'amount', 'remaining' => 'remaining'],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return PurchaseInvoiceResource::collection($invoices);
    }

    /**
     * The supplier's items; a row opens the item.
     */
    public function items(Request $request, int $supplier): JsonResponse
    {
        $record = Supplier::withTrashed()->findOrFail($supplier);
        Gate::authorize('view', $record);
        Gate::authorize('viewAny', Item::class);

        $items = $this->listRecords(
            $record->items()->getQuery(),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'name' => 'name'],
            defaultSort: ['column' => 'id', 'direction' => 'asc'],
        );

        return response()->json([
            'data' => $items->getCollection()->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'image_url' => filled($item->image) ? Storage::disk('public')->url($item->image) : null,
                'is_active' => (bool) $item->is_active,
            ])->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    /**
     * What the supplier is owed now, written for the pay dialog.
     */
    public function debt(Supplier $supplier): JsonResponse
    {
        Gate::authorize('view', $supplier);

        $debt = (new DistributeSupplierPayoff)->debtOf($supplier);
        $formatted = FormattedValue::of($debt, Currency::ChineseYuan);

        return response()->json([
            'debt' => $debt,
            'description' => __('filament/resources/supplier.actions.distribute_payoff.debt', ['debt' => "{$formatted->number} {$formatted->currency}"]),
        ]);
    }

    /**
     * Pays one amount on the supplier's purchase invoices, from the oldest to the newest. DistributeSupplierPayoff checks the permission.
     */
    public function distributePayoff(Request $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('create', Payoff::class);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
        ], attributes: [
            'amount' => __('filament/resources/supplier.actions.distribute_payoff.amount'),
            'date' => __('filament/resources/supplier.actions.distribute_payoff.date'),
        ]);

        (new DistributeSupplierPayoff)->handle($request->user(), $supplier, (float) $data['amount'], $data['date']);

        return response()->json(['message' => __('filament/resources/supplier.actions.distribute_payoff.done')]);
    }

    /**
     * Uploads a supplier's contract to suppliers/contracts: a PDF or an image, up to 10 MB, like the panel.
     */
    public function upload(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        return $this->storeUpload($request, 'suppliers/contracts', ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 10240, __('filament/resources/supplier.fields.contract'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_zh' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contract' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! preg_match('#^suppliers/contracts/[A-Za-z0-9._-]+$#', (string) $value) || ! Storage::disk('public')->exists((string) $value)) {
                    $fail(__('validation.exists', ['attribute' => __('filament/resources/supplier.fields.contract')]));
                }
            }],
            'notes' => ['nullable', 'string'],
        ], attributes: collect(['name_ar', 'name_zh', 'phone', 'email', 'contract', 'notes'])
            ->mapWithKeys(fn (string $field): array => [$field => __("filament/resources/supplier.fields.{$field}")])
            ->all());
    }
}
