<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Requests\Api\V1\OrderIndexRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Invoices\InvoiceDocument;
use App\Models\Order;
use App\Orders\Checkout\Checkout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * The orders as the app lists them: searched by the customer's name or the order number, narrowed to one customer,
     * and sorted by date or by amount. Newest first when the app does not ask for a sort. Deleted orders never appear.
     */
    public function index(OrderIndexRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with('customer')
            ->withCount('items')
            ->when($request->filled('customer_id'), fn (Builder $query): Builder => $query
                ->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('search'), fn (Builder $query): Builder => $query
                ->where(function (Builder $query) use ($request): void {
                    $search = $request->string('search')->value();

                    $query->whereHas('customer', fn (Builder $customer): Builder => $customer
                        ->whereLike('name', '%'.$search.'%', caseSensitive: false));

                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                }))
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->orderBy('id', $request->sortDirection())
            ->paginate($request->integer('per_page') ?: 25)
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * One order with its sold lines, for the order's page in the application.
     */
    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['customer', 'items.item', 'items.lensPower'])->loadCount('items'));
    }

    /**
     * The invoice of one order as a PDF file, the same invoice the panel prints. The application shares this file,
     * on WhatsApp for instance, and whoever gets it can print it. It never carries a cost or a profit.
     */
    public function invoice(Order $order, InvoiceDocument $document): Response
    {
        return response($document->pdf($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$order->id.'.pdf"',
        ]);
    }

    /**
     * The sale: the checkout does it, so the API sells under the same rules as the panel. The prices come from the
     * items in the database, and a rule the checkout rejects comes back as a 422 with its message in the request's language.
     *
     * @throws ValidationException
     */
    public function store(CheckoutRequest $request, Checkout $checkout): JsonResponse
    {
        $order = $checkout->handle($request->user(), $request->toCheckoutData());

        return (new OrderResource($order->refresh()->load('customer')->loadCount('items')))
            ->response()
            ->setStatusCode(201);
    }
}
