<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Enums\OrderPaymentType;
use App\Filament\Pages\InvoicePrinting;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Sales\OrderResource;
use App\Http\Resources\Admin\Sales\SalesPayload;
use App\Models\Color;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\UpdateOrder;
use App\Orders\WeeklyPaymentReminders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The orders (the panel's OrderResource): the list, an order's page, the sale and its edit on the checkout cart, and delete.
 * A sale is made through the app's Checkout and edited through UpdateOrder, so every sale rule and its message are theirs.
 * Viewing and editing take order.view and order.update; adding, deleting, and restoring are for super users (OrderPolicy).
 * What an order cost us and its profit go only to a user with item.view_cost_price.
 */
class OrderController extends Controller
{
    use ListsRecords, MergesTopLevelFilters;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $sortable = ['id' => 'id', 'date' => 'date', 'total_amount' => 'total_amount', 'down_payment' => 'down_payment', 'weekly_payment' => 'weekly_payment', 'remaining' => 'remaining'];

        if (SalesPayload::seesCost()) {
            $sortable += ['total_cost' => 'total_cost', 'profit' => 'profit'];
        }

        $this->mergeTopLevelFilters($request, ['customer_id']);

        $orders = $this->listRecords(
            self::listQuery(),
            $request,
            searchable: ['id', fn (Builder $query, string $search) => $query->orWhereHas('customer', fn (Builder $customer) => $customer->withTrashed()->whereLike('name', "%{$search}%", caseSensitive: false))],
            sortable: $sortable,
            filters: [
                'customer_id' => fn (Builder $query, string $value) => $query->where('customer_id', (int) $value),
                'payment_type' => fn (Builder $query, string $value) => $query->where('payment_type', $value),
            ],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return OrderResource::collection($orders)->additional([
            'can' => ['create' => Gate::allows('create', Order::class), 'see_cost' => SalesPayload::seesCost()],
            'payment_types' => array_map(fn (OrderPaymentType $type): ?array => SalesPayload::enum($type), OrderPaymentType::cases()),
        ]);
    }

    /**
     * The query of a list of orders with what a row shows, read together.
     *
     * @return Builder<Order>
     */
    public static function listQuery(): Builder
    {
        return Order::query()
            ->with(['customer' => fn ($query) => $query->withTrashed(), 'cashier', 'user'])
            ->withCount('items');
    }

    /**
     * An order's page: its details, its amounts, its weekly payment, its lines, its payments, its print links, and what may be done.
     */
    public function show(int $order): JsonResponse
    {
        $record = Order::withTrashed()->with(['customer' => fn ($query) => $query->withTrashed(), 'cashier', 'user', 'items.item' => fn ($query) => $query->withTrashed(), 'items.lensPower' => fn ($query) => $query->withTrashed()])->findOrFail($order);
        Gate::authorize('view', $record);

        $seesCost = SalesPayload::seesCost();
        $resource = new OrderResource($record);
        $colorNames = Color::withTrashed()
            ->whereIn('id', $record->items->flatMap(fn (OrderItem $line): array => array_column(Item::colorMix($line->colors), 'color_id'))->unique()->all())
            ->pluck('name', 'id');

        return response()->json(['data' => [
            ...$resource->resolve(request()),
            'notes' => $record->notes,
            'discount' => round((float) $record->amount - (float) $record->total_amount, 2),
            'lines' => $record->items->map(fn (OrderItem $line): array => [
                'id' => $line->id,
                'item' => $line->item === null ? null : ['id' => $line->item->id, 'name' => $line->item->name],
                'power' => $line->lensPower?->value === null ? null : (string) $line->lensPower->value,
                'colors' => array_map(fn (array $color): array => [
                    'name' => (string) ($colorNames[$color['color_id']] ?? ''),
                    'pieces' => $color['pieces'],
                ], Item::colorMix($line->colors)),
                'quantity' => (float) $line->quantity,
                'packets' => round($line->packets(), 2),
                'pieces' => (float) $line->pieces,
                'price' => (float) $line->price,
                'total_price' => (float) $line->total_price,
                'cost_price' => $seesCost ? (float) $line->cost_price : null,
                'profit' => $seesCost ? (float) $line->profit : null,
            ])->values(),
            'payments' => $record->payment_type === OrderPaymentType::Postpaid ? $this->paymentsOf($record) : null,
            'weekly' => $this->weeklyOf($record),
            'print' => Gate::allows('viewAny', InvoicePrinting::class) ? $resource->printLinks() : null,
            'can' => [
                ...SalesPayload::abilities($record),
                'pay' => ! $record->trashed() && Gate::allows('create', CustomerPayment::class) && Order::query()->debts()->whereKey($record->getKey())->exists(),
                'see_cost' => $seesCost,
            ],
        ]]);
    }

    /**
     * The edit form of an order, filled with it: its customer, payment type, date, notes, weekly payment, discount, and its lines
     * as the cart takes them. An order with payments keeps its customer and its payment type, so the form locks them.
     */
    public function edit(Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $order->load(['customer' => fn ($query) => $query->withTrashed()]);

        return response()->json(['data' => [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer?->name,
            'payment_type' => $order->payment_type?->value,
            'date' => $order->date?->toDateString(),
            'notes' => $order->notes,
            'weekly_payment' => $order->weekly_payment === null ? null : (float) $order->weekly_payment,
            'down_payment' => $order->down_payment === null ? null : (float) $order->down_payment,
            'discount' => round((float) $order->amount - (float) $order->total_amount, 2),
            'has_payments' => $order->allocations()->exists(),
            'lines' => array_values(array_map(fn (array $line): array => [
                'item_id' => $line['item_id'],
                'lens_power_id' => $line['lens_power_id'],
                'colors' => $line['colors'],
                'packets' => $line['packets'],
            ], (new CheckoutCart($order))->savedLines())),
        ]]);
    }

    /**
     * The item cards and the cart summary of the cart being built, for a new sale or for the edit of an order (order_id).
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate(['order_id' => ['nullable', 'integer']]);
        $order = $request->filled('order_id') ? Order::query()->findOrFail($request->integer('order_id')) : null;
        Gate::authorize($order === null ? 'create' : 'update', $order ?? Order::class);

        $cart = CheckoutCart::from($request->all(), $order);

        return response()->json([
            'items' => $cart->checkoutItems(),
            'summary' => $cart->summary(),
        ]);
    }

    /**
     * Makes the sale from the cart through the checkout. An empty cart, or a rule the checkout rejects, comes back as a field error.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $data = $this->validated($request);
        $cart = $this->cartOf($request);

        $order = app(Checkout::class)->handle($request->user(), new CheckoutData(
            customerId: filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null,
            paymentType: OrderPaymentType::tryFrom((string) ($data['payment_type'] ?? '')),
            date: $data['date'],
            lines: $cart->lines(),
            notes: $data['notes'] ?? null,
            downPayment: filled($data['down_payment'] ?? null) ? (float) $data['down_payment'] : null,
            weeklyPayment: filled($data['weekly_payment'] ?? null) ? (float) $data['weekly_payment'] : null,
            discount: $cart->discount(),
        ));

        return response()->json(['data' => ['id' => $order->id], 'message' => __('admin/sales.orders.created')], 201);
    }

    /**
     * Saves the edit of an order from the cart through UpdateOrder. A customer or payment type the form did not send (locked on an
     * order with payments) keeps the order's own.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $data = $this->validated($request);
        $cart = $this->cartOf($request, $order);

        app(UpdateOrder::class)->handle($order, new CheckoutData(
            customerId: filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : (int) $order->customer_id,
            paymentType: OrderPaymentType::tryFrom((string) ($data['payment_type'] ?? '')) ?? $order->payment_type,
            date: $data['date'],
            lines: $cart->lines(),
            notes: $data['notes'] ?? null,
            weeklyPayment: filled($data['weekly_payment'] ?? null) ? (float) $data['weekly_payment'] : null,
            discount: $cart->discount(),
        ));

        return response()->json(['data' => ['id' => $order->id], 'message' => __('admin.actions.saved')]);
    }

    /**
     * Deletes an order. An order with payments on it is refused by the order itself, with an error on the order.
     */
    public function destroy(Order $order): JsonResponse
    {
        Gate::authorize('delete', $order);

        $order->delete();

        return response()->json(status: 204);
    }

    public function restore(int $order): JsonResponse
    {
        $record = Order::onlyTrashed()->findOrFail($order);
        Gate::authorize('restore', $record);

        $record->restore();

        return response()->json(['data' => ['id' => $record->id]]);
    }

    /**
     * The fields of the sale apart from its lines, checked for their type. The sale rules themselves are the checkout's.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'payment_type' => ['nullable', Rule::enum(OrderPaymentType::class)],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'down_payment' => ['nullable', 'numeric'],
            'weekly_payment' => ['nullable', 'numeric'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines' => ['present', 'array'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.lens_power_id' => ['nullable', 'integer'],
            'lines.*.colors' => ['nullable', 'array'],
            'lines.*.packets' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'customer_id' => __('filament/resources/order.fields.customer_id'),
            'payment_type' => __('filament/resources/order.fields.payment_type'),
            'date' => __('filament/resources/order.fields.date'),
            'notes' => __('filament/resources/order.fields.notes'),
            'down_payment' => __('filament/resources/order.fields.down_payment'),
            'weekly_payment' => __('filament/resources/order.fields.weekly_payment'),
            'discount' => __('filament/resources/order.fields.discount'),
            'discount_percent' => __('filament/resources/order.fields.discount'),
            'lines' => __('filament/resources/order.fields.items'),
            'lines.*.packets' => __('filament/resources/order.fields.packets'),
        ]);
    }

    /**
     * The cart of the request. An empty cart is refused, as on the panel.
     *
     * @throws ValidationException
     */
    private function cartOf(Request $request, ?Order $order = null): CheckoutCart
    {
        $cart = CheckoutCart::from(['lines' => $request->input('lines', []), 'discount' => $request->input('discount'), 'discount_percent' => $request->input('discount_percent')], $order);

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('filament/resources/order.checkout.cart_empty')]);
        }

        return $cart;
    }

    /**
     * The payments on the order, oldest first, each with what of it went to this order and its receipt. A deleted payment is not on the order.
     *
     * @return list<array<string, mixed>>
     */
    private function paymentsOf(Order $order): array
    {
        return $order->allocations()->with('payment.user')->get()
            ->filter(fn (CustomerPaymentOrder $allocation): bool => $allocation->payment !== null)
            ->sortBy(fn (CustomerPaymentOrder $allocation): string => $allocation->payment->date->toDateString().'|'.str_pad((string) $allocation->id, 12, '0', STR_PAD_LEFT))
            ->map(fn (CustomerPaymentOrder $allocation): array => [
                'id' => $allocation->payment->id,
                'date' => $allocation->payment->date->toDateString(),
                'amount' => (float) $allocation->amount,
                'is_down_payment' => (bool) $allocation->payment->is_down_payment,
                'user' => $allocation->payment->user?->name,
                'receipt_url' => route('customer-payments.receipt', $allocation->payment),
            ])
            ->values()
            ->all();
    }

    /**
     * What is due on a postpaid order with a weekly payment today: what is late and since how many days, the next weekly payment,
     * and what is left with how many weekly payments. Null on any other order.
     *
     * @return array<string, mixed>|null
     */
    private function weeklyOf(Order $order): ?array
    {
        if ($order->trashed() || $order->payment_type !== OrderPaymentType::Postpaid || blank($order->weekly_payment)) {
            return null;
        }

        $reminder = (new WeeklyPaymentReminders)->ordersOn(today())->first(fn (array $row): bool => $row['order']->is($order));
        $format = fn (float $amount): string => (string) Number::format($amount, maxPrecision: 2);
        $next = $reminder['next_due_date'] ?? null;
        $isLate = ($reminder['late'] ?? 0) > 0.0001;

        return [
            'amount' => (float) $order->weekly_payment,
            'is_late' => $isLate,
            'late_text' => $isLate
                ? __('filament/resources/order.weekly.late_value', ['amount' => $format($reminder['late']), 'days' => (int) $reminder['late_since']->diffInDays(today())])
                : __('filament/pages/customer_payments.order_reminders.not_late'),
            'is_due_today' => $next?->isToday() ?? false,
            'next_text' => match (true) {
                $reminder === null => __('filament/resources/order.weekly.settled'),
                $next === null => __('filament/pages/customer_payments.order_reminders.no_next'),
                $next->isToday() => __('filament/resources/order.weekly.next_today', ['amount' => $format($reminder['next_amount'])]),
                default => __('filament/resources/order.weekly.next_value', ['amount' => $format($reminder['next_amount']), 'date' => $next->format('Y/m/d')]),
            },
            'left_text' => $reminder === null
                ? __('filament/resources/order.weekly.settled')
                : __('filament/resources/order.weekly.left_value', ['amount' => $format($reminder['remaining']), 'weeks' => $reminder['weeks_left']]),
        ];
    }
}
