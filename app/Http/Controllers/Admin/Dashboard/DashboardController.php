<?php

namespace App\Http\Controllers\Admin\Dashboard;

use App\Admin\Pages\FinancialDashboard;
use App\Admin\Pages\MonitoringDashboard;
use App\Http\Controllers\Controller;
use App\Invoices\TransferCashierMoney;
use App\Lenses\InactiveItemsWithStockLens;
use App\Lenses\ItemsSoldAtALossLens;
use App\Lenses\ItemsWithALowMarginLens;
use App\Lenses\ItemsWithANegativeQuantityLens;
use App\Lenses\ItemsWithoutSalesLens;
use App\Lenses\ItemsWithoutSupplierLens;
use App\Lenses\Lens;
use App\Metrics\Cards\AccountantBalanceMetric;
use App\Metrics\Cards\CashSalesBoxMetric;
use App\Metrics\Cards\GeneralExpensesMetric;
use App\Metrics\Cards\InvoiceCountMetric;
use App\Metrics\Cards\PostpaidSalesBoxMetric;
use App\Metrics\Cards\PurchaseExpensesMetric;
use App\Metrics\Cards\RevenuesMetric;
use App\Metrics\Cards\StockValueMetric;
use App\Metrics\Cards\TotalDebtMetric;
use App\Metrics\Cards\ValueMetric;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\User;
use App\Orders\WeeklyPaymentReminders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The dashboards of the admin application: the financial cards, the monitoring lens counts, the cashier money transfer,
 * and the weekly payment reminders of the home page. The numbers come from the cards of app/Metrics/Cards and the lenses of app/Lenses.
 */
class DashboardController extends Controller
{
    /**
     * The financial cards by their key, in the order the dashboard shows them.
     *
     * @var array<string, class-string<ValueMetric>>
     */
    public const FINANCIAL_CARDS = [
        'accountant_balance' => AccountantBalanceMetric::class,
        'cash_sales_box' => CashSalesBoxMetric::class,
        'postpaid_sales_box' => PostpaidSalesBoxMetric::class,
        'general_expenses' => GeneralExpensesMetric::class,
        'purchase_expenses' => PurchaseExpensesMetric::class,
        'revenues' => RevenuesMetric::class,
        'invoice_count' => InvoiceCountMetric::class,
        'total_debt' => TotalDebtMetric::class,
        'stock_value' => StockValueMetric::class,
    ];

    /**
     * The lenses the monitoring dashboard counts.
     *
     * @var list<class-string<Lens>>
     */
    private const LENSES = [
        ItemsWithoutSupplierLens::class,
        InactiveItemsWithStockLens::class,
        ItemsWithANegativeQuantityLens::class,
        ItemsSoldAtALossLens::class,
        ItemsWithALowMarginLens::class,
        ItemsWithoutSalesLens::class,
    ];

    /**
     * The financial dashboard: its cards' keys, and whether the cashier money transfer card shows.
     */
    public function financial(): JsonResponse
    {
        Gate::authorize('viewAny', FinancialDashboard::class);

        return response()->json([
            'title' => __('filament/pages/financial_dashboard.title'),
            'cards' => array_keys(self::FINANCIAL_CARDS),
            'can_transfer_cashier_money' => Gate::allows('transferCashierMoney', FinancialDashboard::class),
        ]);
    }

    /**
     * One financial card at the chosen range.
     */
    public function metric(Request $request, string $key): JsonResponse
    {
        Gate::authorize('viewAny', FinancialDashboard::class);
        abort_unless(array_key_exists($key, self::FINANCIAL_CARDS), 404);

        $card = app(self::FINANCIAL_CARDS[$key]);

        return response()->json(['key' => $key, ...$card->toPayload($request->query('range'))]);
    }

    /**
     * The cashiers with the money on each, for the cashier money transfer card.
     */
    public function cashiers(): JsonResponse
    {
        Gate::authorize('transferCashierMoney', FinancialDashboard::class);

        $transfer = new TransferCashierMoney;

        return response()->json([
            'label' => __('metrics.cards.cashier_money_transfer'),
            'explanation' => __('metrics.cards_info.cashier_money_transfer.explanation'),
            'example' => __('metrics.cards_info.cashier_money_transfer.example'),
            'description' => $transfer->description()?->name,
            'can_transfer' => Gate::allows('create', Invoice::class),
            'cashiers' => User::query()->where('is_cashier', true)->orderBy('name')->get()
                ->map(function (User $cashier) use ($transfer): array {
                    $money = $transfer->moneyOn($cashier);

                    return ['id' => $cashier->id, 'name' => $cashier->name, 'money' => $money, 'money_text' => FormattedValue::of($money, Currency::IraqiDinar)->number];
                })
                ->all(),
        ]);
    }

    /**
     * Moves all the money on a cashier to the accountant's box. The rules are TransferCashierMoney's.
     */
    public function transfer(Request $request): JsonResponse
    {
        Gate::authorize('transferCashierMoney', FinancialDashboard::class);

        $cashier = User::query()->where('is_cashier', true)->findOrFail($request->validate(['cashier_id' => ['required', 'integer']])['cashier_id']);
        $transfer = new TransferCashierMoney;
        $amount = $transfer->moneyOn($cashier);

        $transfer->handle($request->user(), $cashier, $amount, today());

        return response()->json([
            'message' => __('metrics.cashier_money_transfer.transferred', [
                'amount' => FormattedValue::of($amount, Currency::IraqiDinar)->number,
                'cashier' => $cashier->name,
            ]),
        ]);
    }

    /**
     * The monitoring dashboard: a card for every lens the user may open, the ones that need attention first.
     */
    public function monitoring(): JsonResponse
    {
        Gate::authorize('viewAny', MonitoringDashboard::class);

        [$needAttention, $calm] = collect(self::LENSES)
            ->filter(fn (string $lens): bool => $lens::canAccess())
            ->map(function (string $lens): array {
                $count = $lens::count();

                return [
                    'key' => $lens::uriKey(),
                    'label' => $lens::label(),
                    'explanation' => $lens::explanation(),
                    'guidance' => $lens::guidance(),
                    'count' => $count,
                    'number' => FormattedValue::of($count)->number,
                    'path' => '/lenses/'.$lens::uriKey(),
                    'needs_attention' => $count > 0,
                ];
            })
            ->partition(fn (array $card): bool => $card['needs_attention']);

        return response()->json([
            'title' => __('filament/pages/monitoring_dashboard.title'),
            'cards' => $needAttention->merge($calm)->values(),
        ]);
    }

    /**
     * The weekly payment reminders of today for the home page: every order with a weekly payment that is not fully paid,
     * the late ones first. The page groups them by customer. Only a user who may see the customer payments gets them,
     * and only one who may record a payment gets the pay buttons.
     */
    public function reminders(): JsonResponse
    {
        if (! Gate::allows('viewAny', CustomerPayment::class)) {
            return response()->json(['visible' => false, 'rows' => [], 'can_pay' => false]);
        }

        $rows = (new WeeklyPaymentReminders)->ordersOn(today())->map(fn (array $row): array => [
            'order_id' => $row['order']->id,
            'customer_id' => $row['order']->customer_id,
            'customer' => $row['order']->customer?->name,
            'phone' => $row['order']->customer?->phone,
            'weekly_payment' => (float) $row['order']->weekly_payment,
            'late' => round($row['late'], 2),
            'late_since' => $row['late_since']?->toDateString(),
            'days_late' => $row['late_since'] === null ? null : (int) $row['late_since']->diffInDays(today()),
            'next_due_date' => $row['next_due_date']?->toDateString(),
            'next_amount' => round($row['next_amount'], 2),
            'remaining' => round($row['remaining'], 2),
            'weeks_left' => $row['weeks_left'],
            'is_due_today' => ($row['next_due_date']?->isToday() ?? false) && $row['next_amount'] > 0.0001,
        ]);

        return response()->json([
            'visible' => true,
            'rows' => $rows->values(),
            'can_pay' => Gate::allows('create', CustomerPayment::class),
        ]);
    }
}
