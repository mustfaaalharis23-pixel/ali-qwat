<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Admin\Pages\InvoicePrinting;
use App\Enums\OrderPaymentType;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Sales\OrderResource;
use App\Http\Resources\Admin\Sales\SalesPayload;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The invoice printing page: every order, newest first, to look one up and print it as a full
 * page or as the small printer's receipt. The period, the customer, the cashier, and the payment type narrow the list. What the
 * order cost us, its profit, and its margin go only to a user who may see the cost. It takes invoice_printing.view.
 */
class InvoicePrintingController extends Controller
{
    use ListsRecords, MergesTopLevelFilters;

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InvoicePrinting::class);

        $sortable = ['id' => 'id', 'date' => 'date', 'total_amount' => 'total_amount', 'customer' => fn (Builder $query, string $direction) => $query
            ->orderBy(Customer::withTrashed()->select('name')->whereColumn('customers.id', 'orders.customer_id'), $direction)];

        if (SalesPayload::seesCost()) {
            $sortable += [
                'total_cost' => 'total_cost',
                'profit' => 'profit',
                'profit_margin_percentage' => fn (Builder $query, string $direction) => $query
                    ->orderByRaw('case when total_amount > 0 then profit / total_amount else 0 end '.($direction === 'asc' ? 'asc' : 'desc')),
            ];
        }

        $request->attributes->set('with_print_links', true);
        $this->mergeTopLevelFilters($request, ['customer_id', 'cashier_id', 'from', 'until']);

        $orders = $this->listRecords(
            OrderController::listQuery(),
            $request,
            searchable: ['id', fn (Builder $query, string $search) => $query->orWhereHas('customer', fn (Builder $customer) => $customer->withTrashed()->whereLike('name', "%{$search}%", caseSensitive: false))],
            sortable: $sortable,
            filters: [
                'from' => fn (Builder $query, string $value) => $query->whereDate('date', '>=', $value),
                'until' => fn (Builder $query, string $value) => $query->whereDate('date', '<=', $value),
                'customer_id' => fn (Builder $query, string $value) => $query->where('customer_id', (int) $value),
                'cashier_id' => fn (Builder $query, string $value) => $query->where('cashier_id', (int) $value),
                'payment_type' => fn (Builder $query, string $value) => $query->where('payment_type', $value),
            ],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return OrderResource::collection($orders)->additional([
            'title' => __('filament/pages/invoice_printing.title'),
            'can' => ['see_cost' => SalesPayload::seesCost()],
            'payment_types' => array_map(fn (OrderPaymentType $type): ?array => SalesPayload::enum($type), OrderPaymentType::cases()),
        ]);
    }
}
