<?php

namespace App\Http\Controllers\Admin\Purchases;

use App\Enums\ExchangeRateSource;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Admin\Purchases\Concerns\StoresUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Purchases\PurchaseInvoiceRequest;
use App\Http\Resources\Admin\Purchases\PurchaseInvoiceResource;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Invoices\RevertInvoiceConfirmation;
use App\Metrics\Cards\PurchaseInvoices\ImporterConfirmedPurchaseInvoicesMetric;
use App\Metrics\Cards\PurchaseInvoices\PendingPurchaseInvoicesMetric;
use App\Metrics\Cards\ValueMetric;
use App\Models\Color;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\Payoff;
use App\Models\SpecificDescription;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The purchase invoices: invoices under the purchases description. The list with its waiting counts and the pie of the suppliers
 * bought from the most; the create page (which fetches the yuan rate from the banks) and the edit page; the invoice's page with its
 * next steps: moving its stage, confirming it for the side it waits for, and, for a super user, taking back its last confirmation.
 * Every rule is the models' and the invoice classes' own; the form's own rules are in PurchaseInvoiceRequest.
 */
class PurchaseInvoiceController extends Controller
{
    use ListsRecords;
    use StoresUploads;

    /**
     * The waiting counts above the list, by their key.
     *
     * @var array<string, class-string<ValueMetric>>
     */
    private const METRICS = [
        'pending' => PendingPurchaseInvoicesMetric::class,
        'importer_confirmed' => ImporterConfirmedPurchaseInvoicesMetric::class,
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = $this->listRecords(
            Invoice::query()->purchases()->with([
                // Read with the rows, so asking each row's abilities (InvoicePolicy asks isPurchase()) reads no description per row.
                'generalDescription' => fn ($query) => $query->withTrashed(),
                'supplier' => fn ($query) => $query->withTrashed(),
                'container',
                'category' => fn ($query) => $query->withTrashed(),
                'user',
                'splitParts:id,split_from_invoice_id',
            ]),
            $request,
            searchable: [
                'id',
                'name',
                fn (Builder $query, string $search) => $query->orWhereHas('supplier', fn (Builder $supplier) => $supplier->whereLike('name_ar', "%{$search}%", caseSensitive: false)),
            ],
            sortable: ['id' => 'id', 'date' => 'date', 'amount' => 'amount', 'remaining' => 'remaining'],
            filters: [
                'supplier_id' => fn (Builder $query, string $value) => $query->where('supplier_id', (int) $value),
                'status' => fn (Builder $query, string $value) => $query->where('status', $value),
                'stage' => fn (Builder $query, string $value) => $query->where('stage', $value),
            ],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return PurchaseInvoiceResource::collection($invoices)->additional([
            'can' => ['create' => Gate::allows('create', Invoice::class), 'view_containers' => Gate::allows('viewAny', Container::class)],
        ]);
    }

    /**
     * What a new invoice starts with: today, the default dollar rate, and the yuan rate the banks give now (kept on the server,
     * so the invoice is saved at it unless the user may change it). When no bank answers, the rate is typed by hand.
     */
    public function create(): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        $bank = PurchaseInvoiceRequest::fetchBankRate();
        $source = $bank['source'] === null ? null : ExchangeRateSource::from($bank['source']);

        return response()->json([
            'date' => today()->toDateString(),
            'dinar_rate' => PurchaseInvoiceRequest::DEFAULT_DINAR_RATE,
            'exchange_rate' => $bank['rate'],
            'exchange_rate_source' => PurchaseInvoiceResource::enum($source ?? ExchangeRateSource::Manual),
            'exchange_rate_source_url' => $source?->url(),
            'bank_answered' => $bank['rate'] !== null,
            'can_change_exchange_rate' => Gate::allows('editExchangeRate', Invoice::class) || $bank['rate'] === null,
            'min_dinar_rate' => PurchaseInvoiceRequest::MIN_DINAR_RATE,
            'max_dinar_rate' => PurchaseInvoiceRequest::MAX_DINAR_RATE,
            'can_view_cost_price' => Gate::allows('viewCostPrice', Item::class),
            'can_create_options' => $this->inlineCreateAbilities(),
        ]);
    }

    public function store(PurchaseInvoiceRequest $request, PurchaseInvoiceWriter $writer): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $writer->create($request);

        return (new PurchaseInvoiceResource($this->loadPage($invoice)))->response()->setStatusCode(201);
    }

    public function show(int $invoice): PurchaseInvoiceResource
    {
        $record = $this->find($invoice);
        Gate::authorize('view', $record);

        return (new PurchaseInvoiceResource($this->loadPage($record)))->additional($this->pageAbilities());
    }

    public function update(PurchaseInvoiceRequest $request, PurchaseInvoiceWriter $writer, int $invoice): PurchaseInvoiceResource
    {
        $record = $this->find($invoice);
        Gate::authorize('update', $record);

        return new PurchaseInvoiceResource($this->loadPage($writer->update($request, $record)));
    }

    /**
     * An invoice that has payoffs cannot be deleted (the model refuses it).
     */
    public function destroy(int $invoice): JsonResponse
    {
        $record = $this->find($invoice);
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(status: 204);
    }

    public function restore(int $invoice): PurchaseInvoiceResource
    {
        $record = Invoice::onlyTrashed()->purchases()->findOrFail($invoice);
        Gate::authorize('restore', $record);

        $record->restore();

        return new PurchaseInvoiceResource($this->loadPage($record));
    }

    /**
     * An invoice that ever had payoffs is never deleted for good, as the panel hides it.
     */
    public function forceDelete(int $invoice): JsonResponse
    {
        $record = $this->find($invoice);
        Gate::authorize('forceDelete', $record);

        if ($record->payoffs()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['invoice' => __('invoices.validation.invoice_has_payoffs')]);
        }

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * Moves the invoice to its next stage, one step forward, for a user allowed to edit it.
     */
    public function advanceStage(int $invoice): JsonResponse
    {
        $record = $this->find($invoice);
        Gate::authorize('update', $record);

        $record->advanceStage();

        return response()->json([
            'message' => __('filament/resources/purchase_invoice.actions.advance_stage.done', ['stage' => $record->stage->getLabel()]),
        ]);
    }

    /**
     * Confirms the invoice for the side it waits for, with its amount written again. ConfirmPurchaseInvoice checks the permission.
     */
    public function confirm(Request $request, int $invoice): JsonResponse
    {
        $record = $this->find($invoice);
        Gate::authorize('view', $record);
        $side = $record->awaitedConfirmation() ?? throw ValidationException::withMessages([
            'status' => __('invoices.validation.invoice_is_not_waiting_for_this_confirmation'),
        ]);

        $amount = $request->validate(
            ['amount' => ['required', 'numeric', 'min:0.01']],
            attributes: ['amount' => __('filament/resources/purchase_invoice.actions.confirm.amount')],
        )['amount'];

        (new ConfirmPurchaseInvoice)->handle($request->user(), $record, (float) $amount, $side);

        return response()->json(['message' => __('filament/resources/purchase_invoice.actions.confirm.done', ['side' => $side->getLabel()])]);
    }

    /**
     * Takes back the invoice's last confirmation. Only a super user may (RevertInvoiceConfirmation checks it).
     */
    public function revertConfirmation(Request $request, int $invoice): JsonResponse
    {
        $record = $this->find($invoice);
        Gate::authorize('view', $record);

        (new RevertInvoiceConfirmation)->handle($request->user(), $record);

        return response()->json(['message' => __('filament/resources/purchase_invoice.actions.revert_confirmation.done')]);
    }

    /**
     * One of the waiting counts above the list.
     */
    public function metric(Request $request, string $key): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);
        abort_unless(array_key_exists($key, self::METRICS), 404);

        $card = app(self::METRICS[$key]);

        return response()->json(['key' => $key, ...$card->toPayload($request->query('range'))]);
    }

    /**
     * The pie of the suppliers bought from the most, by the amounts of their purchase invoices, the rest as others.
     */
    public function chart(): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $purchases = Invoice::purchasesBySupplier();

        return response()->json(self::pie(
            __('filament/resources/purchase_invoice.widgets.purchases_chart.heading'),
            array_map(fn (array $supplier): array => ['name' => (string) $supplier['name'], 'value' => (float) $supplier['amount']], $purchases['suppliers']),
            (float) $purchases['others'],
        ));
    }

    /**
     * A supplier pie as the browser draws it: its heading and its parts, the others last.
     *
     * @param  list<array{name: string, value: float}>  $suppliers
     * @return array{heading: string, parts: list<array{name: string, value: float, is_others: bool}>}
     */
    public static function pie(string $heading, array $suppliers, float $others): array
    {
        $parts = array_map(fn (array $supplier): array => [...$supplier, 'is_others' => false], $suppliers);

        if ($others > 0) {
            $parts[] = ['name' => __('filament/components/supplier_pie_chart.others'), 'value' => $others, 'is_others' => true];
        }

        return ['heading' => $heading, 'parts' => $parts];
    }

    /**
     * The items a line can buy: the specific descriptions of an item, searched by name.
     */
    public function lineOptions(Request $request): JsonResponse
    {
        $query = SpecificDescription::query()->whereNotNull('item_id')->with('item')->orderBy('name');
        $term = trim((string) $request->query('search', ''));

        if ($request->filled('ids')) {
            $query->withTrashed()->whereKey((array) $request->query('ids'));
        } elseif ($term !== '') {
            $query->whereLike('name', "%{$term}%", caseSensitive: false);
        }

        return response()->json([
            'data' => $query->limit(50)->get()->map(fn (SpecificDescription $description): array => [
                'value' => $description->id,
                'label' => $description->name,
                'description' => $description->item?->pieces_per_packet === null
                    ? null
                    : __('filament/resources/invoice_item.fields.pieces_per_packet').': '.$description->item->pieces_per_packet,
            ])->values(),
        ]);
    }

    /**
     * The lens powers of a group, written in hundreds (150 for 1.50) as the panel writes them.
     */
    public function lensPowerOptions(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('search', ''));
        $query = LensPower::query()->orderBy('value');

        if ($request->filled('ids')) {
            $query->withTrashed()->whereKey((array) $request->query('ids'));
        }

        return response()->json([
            'data' => $query->get()
                ->filter(fn (LensPower $power): bool => $term === '' || $request->filled('ids') || str_contains($power->inHundreds(), $term) || str_contains((string) $power->value, $term))
                ->take(50)
                ->map(fn (LensPower $power): array => ['value' => $power->id, 'label' => $power->inHundreds(), 'description' => null])
                ->values(),
        ]);
    }

    /**
     * What the form needs to know about the item of a line as it is chosen: its packet (which locks the packet field),
     * what is in the stock and on its way, its sale price, and, to whoever may see it, its cost of one dozen now.
     */
    public function lineItem(int $description): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $item = SpecificDescription::withTrashed()->with('item')->findOrFail($description)->item ?? abort(404);

        return response()->json([
            'item_id' => $item->id,
            'name' => $item->name,
            'image_url' => filled($item->image) ? Storage::disk('public')->url($item->image) : null,
            'pieces_per_packet' => $item->pieces_per_packet,
            'available_quantity' => (float) $item->available_quantity,
            'incoming_quantity' => (float) $item->incoming_quantity,
            'sale_price' => (float) $item->sale_price,
            'cost_price' => Gate::allows('viewCostPrice', Item::class) ? (float) $item->cost_price : null,
        ]);
    }

    /**
     * What the chosen supplier is owed now, for the hints under the initial payment.
     */
    public function supplierDebt(int $supplier): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        return response()->json(['debt' => Supplier::withTrashed()->findOrFail($supplier)->totalDebt()]);
    }

    /**
     * Uploads an image of an invoice (to invoices/) or of a payoff (to payoffs/): jpeg, png, or webp, up to 2 MB, like the panel.
     */
    public function upload(Request $request): JsonResponse
    {
        $kind = $request->validate(['kind' => ['required', 'in:images,payoff']])['kind'];

        if ($kind === 'images') {
            Gate::authorize('viewAny', Invoice::class);

            return $this->storeUpload($request, 'invoices', ['jpg', 'jpeg', 'png', 'webp'], 2048, __('filament/resources/invoice.fields.images'));
        }

        Gate::authorize('viewAny', Payoff::class);

        return $this->storeUpload($request, 'payoffs', ['jpg', 'jpeg', 'png', 'webp'], 2048, __('filament/resources/payoff.fields.image'));
    }

    /**
     * A purchase invoice by its id, a deleted one too, so its page still opens.
     */
    private function find(int $id): Invoice
    {
        return Invoice::withTrashed()->purchases()->findOrFail($id);
    }

    /**
     * The invoice with everything its page shows, read together instead of once per line.
     */
    private function loadPage(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'supplier' => fn ($query) => $query->withTrashed(),
            'category' => fn ($query) => $query->withTrashed(),
            'container',
            'user',
            'importerChecker',
            'receiverChecker',
            'splitFrom',
            'splitParts:id,split_from_invoice_id',
            'items.item' => fn ($query) => $query->withTrashed(),
            'items.specificDescription' => fn ($query) => $query->withTrashed(),
            'items.groups.lensPower' => fn ($query) => $query->withTrashed(),
            'items.colors.color' => fn ($query) => $query->withTrashed(),
        ]);
    }

    /**
     * What the invoice's page may link to or change beside the invoice itself.
     *
     * @return array{can: array<string, bool|array<string, bool>>}
     */
    private function pageAbilities(): array
    {
        return ['can' => [
            'edit_exchange_rate' => Gate::allows('editExchangeRate', Invoice::class),
            'view_containers' => Gate::allows('viewAny', Container::class),
            'view_items' => Gate::allows('viewAny', Item::class),
            'view_payoffs' => Gate::allows('viewAny', Payoff::class),
            'create_payoffs' => Gate::allows('create', Payoff::class),
            'view_cost_price' => Gate::allows('viewCostPrice', Item::class),
            'create_options' => $this->inlineCreateAbilities(),
        ]];
    }

    /**
     * Which choices of the form the user may add from inside it (the panel's create option actions): a color, an invoice
     * category, and a lens power. Their own endpoints still check the same abilities.
     *
     * @return array{colors: bool, invoice_categories: bool, lens_powers: bool}
     */
    private function inlineCreateAbilities(): array
    {
        return [
            'colors' => Gate::allows('create', Color::class),
            'invoice_categories' => Gate::allows('create', InvoiceCategory::class),
            'lens_powers' => Gate::allows('create', LensPower::class),
        ];
    }
}
