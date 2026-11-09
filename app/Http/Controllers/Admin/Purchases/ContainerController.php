<?php

namespace App\Http\Controllers\Admin\Purchases;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Purchases\ContainerResource;
use App\Http\Resources\Admin\Purchases\PurchaseInvoiceResource;
use App\Invoices\SplitPurchaseInvoice;
use App\Metrics\Cards\Containers\ContainerCbmMetric;
use App\Metrics\Cards\Containers\ContainerTotalMetric;
use App\Metrics\Cards\Containers\ContainerTransportCostMetric;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The containers: their numbers, their own price of one CBM (a note only), and their dates. A container's page shows its CBM and
 * transport cost and its purchase invoices: invoices in no container are added to it and taken out of it, and part of an invoice
 * is shipped in it (SplitPurchaseInvoice). Changing a container's invoices needs the container's edit permission.
 */
class ContainerController extends Controller
{
    use ListsRecords;

    /**
     * The expected arrival date is filled this many days after the loading date; the user can still change it.
     */
    public const int DAYS_TO_ARRIVAL = 60;

    /**
     * The total cards of a container's page, by their key.
     *
     * @var array<string, class-string<ContainerTotalMetric>>
     */
    private const METRICS = [
        'cbm' => ContainerCbmMetric::class,
        'transport_cost' => ContainerTransportCostMetric::class,
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Container::class);

        $containers = $this->listRecords(
            $this->withInvoiceFacts(Container::query()),
            $request,
            searchable: ['id', 'international_number'],
            sortable: ['id' => 'id', 'cbm_price' => 'cbm_price', 'loading_date' => 'loading_date', 'expected_arrival_date' => 'expected_arrival_date'],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        return ContainerResource::collection($containers)->additional([
            'can' => ['create' => Gate::allows('create', Container::class)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Container::class);

        return (new ContainerResource(Container::query()->create($this->validated($request))))->response()->setStatusCode(201);
    }

    public function show(int $container): ContainerResource
    {
        $record = $this->withInvoiceFacts(Container::withTrashed())->findOrFail($container);
        Gate::authorize('view', $record);

        return (new ContainerResource($record))->additional([
            'days_to_arrival' => self::DAYS_TO_ARRIVAL,
            'can' => ['view_invoices' => Gate::allows('viewAny', Invoice::class)],
        ]);
    }

    public function update(Request $request, Container $container): ContainerResource
    {
        Gate::authorize('update', $container);

        $container->update($this->validated($request, $container));

        return new ContainerResource($this->withInvoiceFacts(Container::query())->findOrFail($container->id));
    }

    /**
     * A container that has invoices cannot be deleted (the model refuses it).
     */
    public function destroy(Container $container): JsonResponse
    {
        Gate::authorize('delete', $container);

        $container->delete();

        return response()->json(status: 204);
    }

    public function restore(int $container): ContainerResource
    {
        $record = Container::onlyTrashed()->findOrFail($container);
        Gate::authorize('restore', $record);

        $record->restore();

        return new ContainerResource($record);
    }

    public function forceDelete(int $container): JsonResponse
    {
        $record = Container::withTrashed()->findOrFail($container);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * One of the totals of a container's page.
     */
    public function metric(Request $request, int $container, string $key): JsonResponse
    {
        $record = Container::withTrashed()->findOrFail($container);
        Gate::authorize('view', $record);
        abort_unless(array_key_exists($key, self::METRICS), 404);

        $card = app(self::METRICS[$key]);
        $card->record = $record;

        return response()->json(['key' => $key, ...$card->toPayload($request->query('range'))]);
    }

    /**
     * The purchase invoices in the container, each with its CBM, its own price of one CBM, and its transport.
     */
    public function invoices(Request $request, int $container): JsonResponse
    {
        $record = Container::withTrashed()->findOrFail($container);
        Gate::authorize('view', $record);

        $invoices = $this->listRecords(
            $record->invoices()->getQuery()->with(['supplier' => fn ($query) => $query->withTrashed()]),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'date' => 'date', 'cbm' => 'cbm'],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return response()->json([
            'data' => $invoices->getCollection()->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'name' => $invoice->name,
                'supplier' => $invoice->supplier?->name_ar,
                'date' => $invoice->date?->toDateString(),
                'cbm' => (float) $invoice->cbm,
                'cbm_price' => $invoice->cbm_price,
                'transport_cost_usd' => $invoice->transport_cost_usd,
                'status' => PurchaseInvoiceResource::enum($invoice->status),
            ])->values(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ],
            'can' => ['change' => $this->canChangeInvoices($record)],
        ]);
    }

    /**
     * The purchase invoices in no container, for the add dialog, searched by name or number.
     */
    public function invoiceOptions(Request $request, int $container): JsonResponse
    {
        $record = Container::withTrashed()->findOrFail($container);
        Gate::authorize('view', $record);

        $term = trim((string) $request->query('search', ''));
        $query = Invoice::query()->purchases()->whereNull('container_id')->with(['supplier' => fn ($query) => $query->withTrashed()])->orderByDesc('date')->orderByDesc('id');

        if ($term !== '') {
            $query->where(fn (Builder $query) => $query->whereLike('name', "%{$term}%", caseSensitive: false)
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('id', (int) $term)));
        }

        return response()->json(['data' => $query->limit(50)->get()->map(fn (Invoice $invoice): array => $this->option($invoice))->values()]);
    }

    /**
     * Puts the chosen invoices that are in no container into this one. Each is saved, so its price of one CBM is locked.
     */
    public function addInvoices(Request $request, int $container): JsonResponse
    {
        $record = Container::query()->findOrFail($container);
        abort_unless($this->canChangeInvoices($record), 403);

        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(fn () => Invoice::query()->purchases()->whereNull('container_id')->whereKey($ids)->get()
            ->each(fn (Invoice $invoice) => $invoice->container()->associate($record)->save()));

        return response()->json(['message' => __('admin/purchases.containers.invoices_added')]);
    }

    /**
     * Takes the chosen invoices out of this container.
     */
    public function takeInvoicesOut(Request $request, int $container): JsonResponse
    {
        $record = Container::query()->findOrFail($container);
        abort_unless($this->canChangeInvoices($record), 403);

        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(fn () => $record->invoices()->whereKey($ids)->get()
            ->each(fn (Invoice $invoice) => $invoice->container()->dissociate()->save()));

        return response()->json(['message' => __('admin/purchases.containers.invoices_taken_out')]);
    }

    /**
     * The purchase invoices that can be split into this container: in no container and not confirmed yet, newest first.
     */
    public function splittable(int $container): JsonResponse
    {
        $record = Container::query()->findOrFail($container);
        abort_unless($this->canChangeInvoices($record), 403);

        return response()->json(['data' => $this->splittableQuery()->with(['supplier' => fn ($query) => $query->withTrashed()])->orderByDesc('date')->get()
            ->map(fn (Invoice $invoice): array => $this->option($invoice))->values()]);
    }

    /**
     * The lines of an invoice that can be split, each with its picture, its packing, its groups and colors, and its cartons.
     */
    public function splittableLines(int $container, int $invoice): JsonResponse
    {
        $record = Container::query()->findOrFail($container);
        abort_unless($this->canChangeInvoices($record), 403);

        $lines = $this->splittableQuery()->findOrFail($invoice)->items()
            ->with(['specificDescription' => fn ($query) => $query->withTrashed(), 'item' => fn ($query) => $query->withTrashed(), 'groups.lensPower', 'colors.color'])
            ->get();

        $split = new SplitPurchaseInvoice;

        return response()->json(['lines' => $lines->map(fn (InvoiceItem $line): array => [
            'id' => $line->id,
            'name' => (string) $line->specificDescription?->name,
            'image_url' => filled($line->item?->image) ? Storage::disk('public')->url($line->item->image) : null,
            'cartons_count' => (int) $line->cartons_count,
            'boxes_count' => (float) $line->boxes_count,
            'boxes_per_carton' => (float) $line->boxes_per_carton,
            'pieces_per_packet' => (float) $line->pieces_per_packet,
            'quantity' => (float) $line->quantity,
            'groups' => $line->groups->map(fn (InvoiceItemGroup $group): string => $group->description())->values(),
            'colors' => $line->colorsDescription(),
            // What each count of cartons carries, worked out by the split itself, so the window previews exactly what it will do.
            'packets_by_cartons' => array_map(fn (int $cartons): float => $split->packetsIn($line, $cartons), range(0, (int) $line->cartons_count)),
        ])->values()]);
    }

    /**
     * Ships part of an invoice in this container: the cartons written for each line become a new invoice in it,
     * and the original keeps the rest. The rules are SplitPurchaseInvoice's.
     */
    public function split(Request $request, int $container): JsonResponse
    {
        $record = Container::query()->findOrFail($container);
        abort_unless($this->canChangeInvoices($record), 403);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'cartons' => ['nullable', 'array'],
            'cartons.*' => ['nullable', 'integer', 'min:0'],
        ], attributes: ['invoice_id' => __('filament/resources/container.actions.add_part_of_invoice.invoice')]);

        $part = (new SplitPurchaseInvoice)->handle(
            Invoice::query()->purchases()->findOrFail($data['invoice_id']),
            $record,
            array_map('intval', array_filter($data['cartons'] ?? [], fn (mixed $cartons): bool => $cartons !== null)),
        );

        return response()->json([
            'message' => __('filament/resources/container.actions.add_part_of_invoice.done', ['name' => $part->name]),
            'invoice_id' => $part->id,
        ]);
    }

    /**
     * The purchase invoices in no container and not confirmed yet.
     *
     * @return Builder<Invoice>
     */
    private function splittableQuery(): Builder
    {
        return Invoice::query()->purchases()->whereNull('container_id')->where('status', '!=', InvoiceStatus::Confirmed);
    }

    /**
     * @return array{value: int, label: string, description: ?string}
     */
    private function option(Invoice $invoice): array
    {
        return [
            'value' => $invoice->id,
            'label' => "#{$invoice->id} · {$invoice->name}",
            'description' => $invoice->supplier?->name_ar,
        ];
    }

    /**
     * Adding invoices to the container or taking them out of it edits the container; a deleted container gets no changes.
     */
    private function canChangeInvoices(Container $container): bool
    {
        return ! $container->trashed() && Gate::allows('update', $container);
    }

    /**
     * The CBM and the transport cost of a container's invoices, and whether it has any (deleted ones too, for a delete for good).
     *
     * @param  Builder<Container>  $query
     * @return Builder<Container>
     */
    private function withInvoiceFacts(Builder $query): Builder
    {
        return $query
            ->withSum('invoices', 'cbm')
            ->withSum('invoices', 'transport_cost_usd')
            ->withExists('invoices as has_invoices')
            ->withExists(['invoices as has_any_invoices' => fn (Builder $query): Builder => $query->withTrashed()]);
    }

    /**
     * The international number in capital letters without spaces, as it is written on the container (ISO 6346).
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Container $container = null): array
    {
        $number = $request->input('international_number');
        $request->merge(['international_number' => filled($number) ? strtoupper(str_replace(' ', '', (string) $number)) : null]);

        return $request->validate([
            'international_number' => ['nullable', 'string', 'regex:/^[A-Z]{4}[0-9]{7}$/', Rule::unique(Container::class, 'international_number')->ignore($container)],
            'cbm_price' => ['required', 'numeric', 'min:0'],
            'loading_date' => ['nullable', 'date'],
            'expected_arrival_date' => ['nullable', 'date', ...($request->filled('loading_date') ? ['after_or_equal:loading_date'] : [])],
            'notes' => ['nullable', 'string'],
        ], attributes: collect(['international_number', 'cbm_price', 'loading_date', 'expected_arrival_date', 'notes'])
            ->mapWithKeys(fn (string $field): array => [$field => __("filament/resources/container.fields.{$field}")])
            ->all());
    }
}
