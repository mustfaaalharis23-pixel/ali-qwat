<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Enums\CustomerPaymentType;
use App\Filament\Resources\Customers\Widgets\CustomerAverageOrderMetric;
use App\Filament\Resources\Customers\Widgets\CustomerDaysSinceMetric;
use App\Filament\Resources\Customers\Widgets\CustomerDebtMetric;
use App\Filament\Resources\Customers\Widgets\CustomerItemsCountMetric;
use App\Filament\Resources\Customers\Widgets\CustomerLastOrderMetric;
use App\Filament\Resources\Customers\Widgets\CustomerLastPaymentMetric;
use App\Filament\Resources\Customers\Widgets\CustomerOrdersCountMetric;
use App\Filament\Resources\Customers\Widgets\CustomerPaidMetric;
use App\Filament\Resources\Customers\Widgets\CustomerSalesMetric;
use App\Filament\Resources\Customers\Widgets\CustomerTotalMetric;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Sales\CustomerPaymentResource;
use App\Http\Resources\Admin\Sales\CustomerResource;
use App\Http\Resources\Admin\Sales\SalesPayload;
use App\Metrics\FormattedValue;
use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The customers (the panel's CustomerResource): the list with each customer's debt, the form with its contract file, the page with
 * its total cards and its payments, delete, restore, and delete forever. The cards are the panel's own widgets, served as JSON.
 */
class CustomerController extends Controller
{
    use ListsRecords;

    /**
     * The total cards of a customer's page by their key, in the order the page shows them: its account, then its buying.
     *
     * @var array<string, class-string<CustomerTotalMetric>>
     */
    public const METRICS = [
        'debt' => CustomerDebtMetric::class,
        'paid' => CustomerPaidMetric::class,
        'last_payment' => CustomerLastPaymentMetric::class,
        'sales' => CustomerSalesMetric::class,
        'orders_count' => CustomerOrdersCountMetric::class,
        'average_order' => CustomerAverageOrderMetric::class,
        'items_count' => CustomerItemsCountMetric::class,
        'last_order' => CustomerLastOrderMetric::class,
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = $this->listRecords(
            Customer::withDebt(Customer::query()),
            $request,
            searchable: ['id', 'name', 'phone', 'email'],
            sortable: ['id' => 'id', 'name' => 'name', 'phone' => 'phone', 'debt' => 'open_debt'],
            filters: [
                'payment_type' => fn (Builder $query, string $value) => $query->where('payment_type', $value),
                'is_invoice_by_invoice' => fn (Builder $query, string $value) => $query->where('is_invoice_by_invoice', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
            ],
        );

        return CustomerResource::collection($customers)->additional([
            'can' => [
                'create' => Gate::allows('create', Customer::class),
                'delete_any' => Gate::allows('deleteAny', Customer::class),
                'restore_any' => Gate::allows('restoreAny', Customer::class),
                'force_delete_any' => Gate::allows('forceDeleteAny', Customer::class),
            ],
            'payment_types' => array_map(fn (CustomerPaymentType $type): ?array => SalesPayload::enum($type), CustomerPaymentType::cases()),
        ]);
    }

    /**
     * A customer's page: its details and debt, the keys of its total cards, and whether its payments can be seen.
     */
    public function show(int $customer): JsonResponse
    {
        $record = Customer::withTrashed()->findOrFail($customer);
        Gate::authorize('view', $record);

        return response()->json([
            'data' => (new CustomerResource($record))->resolve(request()),
            'metrics' => array_keys(self::METRICS),
            'can' => [
                'view_payments' => Gate::allows('viewAny', CustomerPayment::class),
                'delete_payments' => Gate::allows('deleteAny', CustomerPayment::class),
            ],
        ]);
    }

    /**
     * One total card of a customer. The days since cards show the date of the last event beside the days, or a dash without one,
     * as the panel shows them.
     */
    public function metric(int $customer, string $key): JsonResponse
    {
        $record = Customer::withTrashed()->findOrFail($customer);
        Gate::authorize('view', $record);
        abort_unless(array_key_exists($key, self::METRICS), 404);

        $card = app(self::METRICS[$key]);
        $card->record = $record;
        $card->mount();
        $payload = $card->toPayload();

        if ($card instanceof CustomerDaysSinceMetric) {
            /** @var FormattedValue $value */
            $value = (fn (): FormattedValue => $this->getViewData()['value'])->call($card);
            $payload['value'] = ['number' => $value->number, 'currency' => $value->currency, 'raw' => $payload['value']['raw']];
        }

        return response()->json(['key' => $key, ...$payload]);
    }

    /**
     * The customer's payments, newest first. They are recorded with the pay action and only deleted, never edited.
     */
    public function payments(Request $request, int $customer): AnonymousResourceCollection
    {
        $record = Customer::withTrashed()->findOrFail($customer);
        Gate::authorize('view', $record);
        Gate::authorize('viewAny', CustomerPayment::class);

        $payments = $this->listRecords(
            CustomerPayment::query()->where('customer_id', $record->id)->with('user'),
            $request,
            searchable: ['id', 'notes'],
            sortable: ['id' => 'id', 'date' => 'date', 'amount' => 'amount'],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return CustomerPaymentResource::collection($payments);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::query()->create($this->validated($request));

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    /**
     * Saves the customer. A new contract file replaces the old one, and remove_contract takes it off; the model removes the old file.
     */
    public function update(Request $request, Customer $customer): CustomerResource
    {
        Gate::authorize('update', $customer);

        $customer->update($this->validated($request, $customer));

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return response()->json(status: 204);
    }

    /**
     * Deletes, restores, or deletes forever the chosen customers together (the panel's bulk actions). Every customer is checked
     * against its policy before any is changed; one not in the state the action needs is left as it is.
     */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['delete', 'restore', 'force_delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ability = Str::camel($data['action']);
        $customers = Customer::withTrashed()->whereKey($data['ids'])->get();
        $customers->each(fn (Customer $customer) => Gate::authorize($ability, $customer));

        $changed = $customers
            ->filter(fn (Customer $customer): bool => match ($data['action']) {
                'delete' => ! $customer->trashed(),
                'restore' => $customer->trashed(),
                default => true,
            })
            ->each(fn (Customer $customer) => match ($data['action']) {
                'delete' => $customer->delete(),
                'restore' => $customer->restore(),
                default => $customer->forceDelete(),
            });

        return response()->json(['count' => $changed->count()]);
    }

    public function restore(int $customer): CustomerResource
    {
        $record = Customer::onlyTrashed()->findOrFail($customer);
        Gate::authorize('restore', $record);

        $record->restore();

        return new CustomerResource($record);
    }

    public function forceDelete(int $customer): JsonResponse
    {
        $record = Customer::withTrashed()->findOrFail($customer);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * The customer's fields as the panel's form checks them, with the contract stored on the public disk.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payment_type' => ['required', Rule::enum(CustomerPaymentType::class)],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_invoice_by_invoice' => ['nullable', 'boolean'],
            'contract' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:10240'],
            'remove_contract' => ['nullable', 'boolean'],
        ], attributes: [
            'name' => __('filament/resources/customer.fields.name'),
            'payment_type' => __('filament/resources/customer.fields.payment_type'),
            'phone' => __('filament/resources/customer.fields.phone'),
            'email' => __('filament/resources/customer.fields.email'),
            'is_invoice_by_invoice' => __('filament/resources/customer.fields.is_invoice_by_invoice'),
            'contract' => __('filament/resources/customer.fields.contract'),
        ]);

        $fields = [
            'name' => $data['name'],
            'payment_type' => $data['payment_type'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_invoice_by_invoice' => (bool) ($data['is_invoice_by_invoice'] ?? false),
        ];

        if ($request->hasFile('contract')) {
            $fields['contract'] = $request->file('contract')->store('customers/contracts', 'public');
        } elseif ($request->boolean('remove_contract') && $customer !== null) {
            $fields['contract'] = null;
        }

        return $fields;
    }
}
