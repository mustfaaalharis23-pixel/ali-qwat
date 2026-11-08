<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Enums\OrderPaymentType;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Sales\CustomerPaymentResource;
use App\Http\Resources\Admin\Sales\SalesPayload;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use App\Models\Order;
use App\Orders\PayCustomerDebt;
use App\Orders\WeeklyPaymentReminders;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * The customer payments page (the panel's CustomerPayments page and its tabs): the payments, the account statement of a customer,
 * the weekly payments due today and the late ones, and the payment window (the panel's PayDebtAction) that records a payment on
 * the orders it is for through PayCustomerDebt. Anyone who can view customer payments opens the page; recording one takes
 * customer_payment.create (and invoice.create, checked by PayCustomerDebt), and a payment is never edited, only deleted.
 */
class CustomerPaymentController extends Controller
{
    use ListsRecords, MergesTopLevelFilters;

    /**
     * The page's tabs: how many customers are due today and how many are late, and whether a payment can be recorded.
     */
    public function overview(): JsonResponse
    {
        Gate::authorize('viewAny', CustomerPayment::class);

        $reminders = new WeeklyPaymentReminders;

        return response()->json([
            'title' => __('filament/pages/customer_payments.title'),
            'counts' => ['due_today' => $reminders->dueOn(today())->count(), 'late' => $reminders->lateOn(today())->count()],
            'can' => ['create' => Gate::allows('create', CustomerPayment::class)],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CustomerPayment::class);

        $this->mergeTopLevelFilters($request, ['customer_id', 'from', 'until']);

        $payments = $this->listRecords(
            CustomerPayment::query()->with(['customer' => fn ($query) => $query->withTrashed(), 'user']),
            $request,
            searchable: ['id', fn (Builder $query, string $search) => $query->orWhereHas('customer', fn (Builder $customer) => $customer->withTrashed()->whereLike('name', "%{$search}%", caseSensitive: false))],
            sortable: ['id' => 'id', 'date' => 'date', 'amount' => 'amount'],
            filters: [
                'customer_id' => fn (Builder $query, string $value) => $query->where('customer_id', (int) $value),
                'from' => fn (Builder $query, string $value) => $query->whereDate('date', '>=', $value),
                'until' => fn (Builder $query, string $value) => $query->whereDate('date', '<=', $value),
            ],
            defaultSort: ['column' => 'date', 'direction' => 'desc'],
        );

        return CustomerPaymentResource::collection($payments)->additional([
            'can' => ['create' => Gate::allows('create', CustomerPayment::class)],
        ]);
    }

    /**
     * A payment's details: who paid how much and when, its withdrawal invoice, the orders it paid with the share of each, and its receipt.
     */
    public function show(int $payment): CustomerPaymentResource
    {
        $record = CustomerPayment::withTrashed()
            ->with(['customer' => fn ($query) => $query->withTrashed(), 'user', 'invoice', 'allocations.order' => fn ($query) => $query->withTrashed()])
            ->findOrFail($payment);
        Gate::authorize('view', $record);

        return new CustomerPaymentResource($record);
    }

    /**
     * Deletes a payment: its orders get their remaining back and its withdrawal invoice is deleted (the payment does it).
     */
    public function destroy(int $payment): JsonResponse
    {
        $record = CustomerPayment::query()->findOrFail($payment);
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(status: 204);
    }

    /**
     * What the payment window shows: the customer (or the customers who owe, to choose one), its debt, and every order it still owes
     * on, oldest first, each with what is left on it, what of it is late, and its weekly payment with the next one. Opened on an order,
     * only that order.
     */
    public function form(Request $request): JsonResponse
    {
        Gate::authorize('create', CustomerPayment::class);

        $order = $request->filled('order_id') ? Order::query()->with('customer')->findOrFail($request->integer('order_id')) : null;
        $customer = $order?->customer ?? ($request->filled('customer_id') ? Customer::query()->find($request->integer('customer_id')) : null);

        if ($customer === null) {
            return response()->json([
                'customer' => null,
                'customers' => Customer::query()
                    ->whereHas('orders', fn (Builder $query): Builder => $query->debts())
                    ->orderBy('name')
                    ->get(['id', 'name', 'phone'])
                    ->map(fn (Customer $customer): array => ['value' => $customer->id, 'label' => $customer->name, 'description' => $customer->phone]),
                'debt' => 0,
                'orders' => [],
            ]);
        }

        $debt = $customer->debt();
        $reminders = (new WeeklyPaymentReminders)->ordersOn(today())->keyBy(fn (array $row): int => $row['order']->id);
        $orders = collect((new PayCustomerDebt)->plan($customer, $debt))
            ->map(fn (array $line): Order => $line['order'])
            ->when($order !== null, fn (Collection $orders) => $orders->filter(fn (Order $open): bool => $open->id === $order->id))
            ->map(function (Order $open) use ($reminders): array {
                $reminder = $reminders->get($open->id);
                $next = $reminder['next_due_date'] ?? null;

                return [
                    'id' => $open->id,
                    'date' => $open->date->toDateString(),
                    'total' => (float) $open->total_amount,
                    'remaining' => (float) $open->remaining,
                    'weekly_payment' => $open->weekly_payment === null ? null : (float) $open->weekly_payment,
                    'late' => (float) ($reminder['late'] ?? 0),
                    'next_amount' => (float) ($reminder['next_amount'] ?? 0),
                    'next_due_date' => $next?->toDateString(),
                    'is_due_today' => $next?->isToday() ?? false,
                ];
            })
            ->values();

        return response()->json([
            'customer' => ['id' => $customer->id, 'name' => $customer->name],
            'order' => $order === null ? null : ['id' => $order->id],
            'debt' => $debt,
            'orders' => $orders,
        ]);
    }

    /**
     * Records one payment of a customer on the orders it is for, each with its own amount, through PayCustomerDebt. A typed amount may
     * carry thousands separators; an order left empty is not paid. A rule the payment breaks comes back as an error.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', CustomerPayment::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'amounts' => ['nullable', 'array'],
            'date' => ['required', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ], attributes: [
            'customer_id' => __('filament/resources/customer.actions.pay_debt.customer'),
            'date' => __('filament/resources/customer.actions.pay_debt.date'),
            'image' => __('filament/resources/customer.actions.pay_debt.image'),
            'notes' => __('filament/resources/customer.actions.pay_debt.notes'),
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $amounts = collect($data['amounts'] ?? [])
            ->mapWithKeys(fn (mixed $amount, int|string $orderId): array => [(int) $orderId => max((float) str_replace(',', '', (string) $amount), 0)])
            ->filter(fn (float $amount): bool => $amount > 0)
            ->all();

        $payment = (new PayCustomerDebt)->payOrders(
            $request->user(),
            $customer,
            $amounts,
            $data['date'],
            $request->hasFile('image') ? $request->file('image')->store('customer-payments', 'public') : null,
            $data['notes'] ?? null,
        );

        return (new CustomerPaymentResource($payment->load(['customer', 'user'])))
            ->additional(['message' => __('filament/resources/customer.actions.pay_debt.done')])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The account statement of a customer inside a period: one line per postpaid order (what it adds to the debt) and per payment
     * (what it takes from it), with the balance after each, a previous balance with a from date, the cards that sum the period, and
     * the orders tree with the payments under each order. Cash orders are paid when they are sold, so they are not in it.
     */
    public function statement(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CustomerPayment::class);

        $customer = $request->filled('customer') ? Customer::withTrashed()->find($request->integer('customer')) : null;

        if ($customer === null) {
            return response()->json(['customer' => null, 'rows' => [], 'summary' => null, 'orders' => []]);
        }

        $from = $request->filled('from') ? CarbonImmutable::parse($request->query('from'))->startOfDay() : null;
        $until = $request->filled('until') ? CarbonImmutable::parse($request->query('until'))->endOfDay() : null;

        [$before, $inside] = $this->entriesOf($customer)->partition(fn (array $entry): bool => $from !== null && $entry['date']->lt($from));
        $inside = $inside->filter(fn (array $entry): bool => $until === null || $entry['date']->lte($until));
        $balance = (float) $before->sum(fn (array $entry): float => ($entry['owed'] ?? 0) - ($entry['paid'] ?? 0));
        $rows = [];

        if ($from !== null) {
            $rows[] = [
                'type' => 'opening_balance',
                'date' => $from->toDateString(),
                'movement' => __('filament/resources/customer.statement.types.opening_balance'),
                'details' => __('filament/resources/customer.statement.details.opening_balance', ['date' => $from->format('Y/m/d')]),
                'owed' => null,
                'paid' => null,
                'balance' => $balance,
                'order_id' => null,
                'image_url' => null,
            ];
        }

        foreach ($inside as $entry) {
            $balance += ($entry['owed'] ?? 0) - ($entry['paid'] ?? 0);
            $rows[] = [...collect($entry)->except('sort')->all(), 'date' => $entry['date']->toDateString(), 'balance' => $balance];
        }

        return response()->json([
            'customer' => ['id' => $customer->id, 'name' => $customer->name],
            'rows' => $rows,
            'summary' => [
                'orders' => (float) $inside->sum('owed'),
                'payments' => (float) $inside->sum('paid'),
                'balance' => $balance,
            ],
            'orders' => $this->orderTreeOf($customer, $request->query('from'), $request->query('until')),
        ]);
    }

    /**
     * A reminders tab: the customers who owe a weekly payment today, or, when late, the customers with weekly payments that fell due
     * before today and are still unpaid, with the date of the oldest and the days since.
     */
    public function reminders(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CustomerPayment::class);

        $late = $request->boolean('late');
        $reminders = new WeeklyPaymentReminders;

        return response()->json([
            'late' => $late,
            'can_pay' => Gate::allows('create', CustomerPayment::class),
            'rows' => ($late ? $reminders->lateOn(today()) : $reminders->dueOn(today()))
                ->map(fn (array $row): array => [
                    'customer' => ['id' => $row['customer']->id, 'name' => $row['customer']->name, 'phone' => $row['customer']->phone],
                    'amount' => round($row['amount'], 2),
                    'due_date' => $row['due_date']->toDateString(),
                    'days_late' => $late ? (int) $row['due_date']->diffInDays(today()) : null,
                ])
                ->values(),
        ]);
    }

    /**
     * Every postpaid order and payment of the customer that is not deleted, from the oldest date to the newest.
     * On the same date, orders come before payments, each in the order they were created.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function entriesOf(Customer $customer): Collection
    {
        $orders = $customer->orders()
            ->where('payment_type', OrderPaymentType::Postpaid)
            ->withCount('items')
            ->get()
            ->map(fn (Order $order): array => [
                'type' => 'order',
                'date' => $order->date->toImmutable(),
                'movement' => __('filament/resources/customer.statement.types.order'),
                'details' => __('filament/resources/customer.statement.details.order', ['id' => $order->id, 'items' => $order->items_count]),
                'owed' => (float) $order->total_amount,
                'paid' => null,
                'order_id' => $order->id,
                'image_url' => null,
                'sort' => [0, $order->id],
            ]);

        $payments = $customer->payments()
            ->with('user')
            ->get()
            ->map(fn (CustomerPayment $payment): array => [
                'type' => $payment->is_down_payment ? 'down_payment' : 'payment',
                'date' => $payment->date->toImmutable(),
                'movement' => $payment->is_down_payment
                    ? __('filament/resources/customer.statement.types.down_payment')
                    : __('filament/resources/customer.statement.types.payment'),
                'details' => collect([
                    __('filament/resources/customer.statement.details.payment_by', ['user' => $payment->user?->name]),
                    $payment->notes,
                ])->filter()->implode(' · '),
                'owed' => null,
                'paid' => (float) $payment->amount,
                'order_id' => null,
                'image_url' => SalesPayload::fileUrl($payment->image),
                'sort' => [1, $payment->id],
            ]);

        return $orders
            ->concat($payments)
            ->sort(fn (array $first, array $second): int => [$first['date'], ...$first['sort']] <=> [$second['date'], ...$second['sort']])
            ->values();
    }

    /**
     * The customer's postpaid orders inside the period, oldest first, each with what it came to, what was paid on it, what is left,
     * its weekly payment with what is late and the next one, and every payment that went to it with what was left after it.
     *
     * @return list<array<string, mixed>>
     */
    private function orderTreeOf(Customer $customer, mixed $from, mixed $until): array
    {
        $reminders = (new WeeklyPaymentReminders)->ordersOn(today())->keyBy(fn (array $row): int => $row['order']->id);
        $format = fn (float $amount): string => (string) Number::format($amount, maxPrecision: 2);

        return $customer->orders()
            ->where('payment_type', OrderPaymentType::Postpaid)
            ->when(filled($from), fn ($query) => $query->whereDate('date', '>=', $from))
            ->when(filled($until), fn ($query) => $query->whereDate('date', '<=', $until))
            ->withCount('items')
            ->with(['allocations.payment.user'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (Order $order) use ($reminders, $format): array {
                $left = (float) $order->total_amount;
                $reminder = $reminders->get($order->id);
                $total = (float) $order->total_amount;
                $paid = (float) $order->paid_amount;
                $next = $reminder['next_due_date'] ?? null;

                return [
                    'id' => $order->id,
                    'date' => $order->date->toDateString(),
                    'items' => (int) $order->items_count,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => (float) $order->remaining,
                    'percent' => $total > 0 ? (int) min(100, round($paid / $total * 100)) : 0,
                    'weekly' => filled($order->weekly_payment) ? (float) $order->weekly_payment : null,
                    'late' => (float) ($reminder['late'] ?? 0),
                    'late_days' => isset($reminder['late_since']) ? (int) $reminder['late_since']->diffInDays(today()) : null,
                    'next_amount' => (float) ($reminder['next_amount'] ?? 0),
                    'next_date' => $next?->toDateString(),
                    'next_text' => match (true) {
                        $reminder === null => null,
                        $next === null => __('filament/pages/customer_payments.order_reminders.no_next'),
                        $next->isToday() => __('filament/pages/customer_payments.order_reminders.next_today', ['amount' => $format($reminder['next_amount'])]),
                        default => __('filament/pages/customer_payments.order_reminders.next', ['amount' => $format($reminder['next_amount']), 'date' => $next->format('Y/m/d')]),
                    },
                    'payments' => $order->allocations
                        ->filter(fn (CustomerPaymentOrder $allocation): bool => $allocation->payment !== null)
                        ->sortBy(fn (CustomerPaymentOrder $allocation): string => $allocation->payment->date->toDateString().'|'.str_pad((string) $allocation->customer_payment_id, 12, '0', STR_PAD_LEFT))
                        ->map(function (CustomerPaymentOrder $allocation) use (&$left): array {
                            $left -= (float) $allocation->amount;

                            return [
                                'date' => $allocation->payment->date->toDateString(),
                                'amount' => (float) $allocation->amount,
                                'left_after' => max($left, 0),
                                'is_down_payment' => (bool) $allocation->payment->is_down_payment,
                                'user' => $allocation->payment->user?->name,
                                'notes' => $allocation->payment->notes,
                                'receipt_url' => route('customer-payments.receipt', $allocation->payment),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }
}
