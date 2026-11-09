<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Pages\FinancialDashboard;
use App\Admin\Pages\InvoicePrinting;
use App\Admin\Pages\MonitoringDashboard;
use App\Http\Controllers\Controller;
use App\Lenses\InactiveItemsWithStockLens;
use App\Lenses\ItemsSoldAtALossLens;
use App\Lenses\ItemsWithALowMarginLens;
use App\Lenses\ItemsWithANegativeQuantityLens;
use App\Lenses\ItemsWithoutSalesLens;
use App\Lenses\ItemsWithoutSupplierLens;
use App\Lenses\Lens;
use App\Models\Audit;
use App\Models\Color;
use App\Models\Container;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\Order;
use App\Models\SpecificDescription;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\GoldenReport;
use App\Reports\ItemSalesReport;
use App\Reports\ProfitAndLossReport;
use App\Reports\PurchasesReport;
use App\Reports\Report;
use App\Reports\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The sidebar of the admin application: the screens the signed-in user may open, in their groups and order.
 * A screen of a model shows to whoever may list the model (viewAny), a page to whoever its Policy lets in,
 * and a lens or a report to whoever it lets in (canAccess). Inside a group the screens are ordered by their sort,
 * then by their label in the current language.
 */
class NavigationController extends Controller
{
    /**
     * The groups in the order they show; the screens without a group come first.
     *
     * @var list<string>
     */
    private const GROUPS = ['sales', 'purchases', 'items_management', 'lenses', 'accounting', 'reports', 'system_administration'];

    /**
     * The screens of the models: their address, their group, their sort, their icon, and their model.
     *
     * @var list<array{key: string, group: string, sort: int, icon: string, model: class-string, label: string}>
     */
    private const MODEL_SCREENS = [
        ['key' => 'orders', 'group' => 'sales', 'sort' => 1, 'icon' => 'o-shopping-bag', 'model' => Order::class, 'label' => 'order'],
        ['key' => 'customers', 'group' => 'sales', 'sort' => 2, 'icon' => 'o-user-group', 'model' => Customer::class, 'label' => 'customer'],
        ['key' => 'invoice-categories', 'group' => 'purchases', 'sort' => 0, 'icon' => 'o-tag', 'model' => InvoiceCategory::class, 'label' => 'invoice_category'],
        ['key' => 'purchase-invoices', 'group' => 'purchases', 'sort' => 1, 'icon' => 'o-shopping-cart', 'model' => Invoice::class, 'label' => 'purchase_invoice'],
        ['key' => 'containers', 'group' => 'purchases', 'sort' => 2, 'icon' => 'o-cube', 'model' => Container::class, 'label' => 'container'],
        ['key' => 'suppliers', 'group' => 'purchases', 'sort' => 3, 'icon' => 'o-truck', 'model' => Supplier::class, 'label' => 'supplier'],
        ['key' => 'colors', 'group' => 'items_management', 'sort' => 0, 'icon' => 'o-swatch', 'model' => Color::class, 'label' => 'color'],
        ['key' => 'lens-powers', 'group' => 'items_management', 'sort' => 0, 'icon' => 'o-eye', 'model' => LensPower::class, 'label' => 'lens_power'],
        ['key' => 'items', 'group' => 'items_management', 'sort' => 1, 'icon' => 'o-cube', 'model' => Item::class, 'label' => 'item'],
        ['key' => 'general-descriptions', 'group' => 'accounting', 'sort' => 1, 'icon' => 'o-tag', 'model' => GeneralDescription::class, 'label' => 'general_description'],
        ['key' => 'general-invoices', 'group' => 'accounting', 'sort' => 2, 'icon' => 'o-document-text', 'model' => Invoice::class, 'label' => 'general_invoice'],
        ['key' => 'specific-descriptions', 'group' => 'accounting', 'sort' => 2, 'icon' => 'o-tag', 'model' => SpecificDescription::class, 'label' => 'specific_description'],
        ['key' => 'users', 'group' => 'system_administration', 'sort' => 1, 'icon' => 'o-rectangle-stack', 'model' => User::class, 'label' => 'user'],
        ['key' => 'audits', 'group' => 'system_administration', 'sort' => 2, 'icon' => 'o-clipboard-document-list', 'model' => Audit::class, 'label' => 'audit'],
    ];

    /**
     * The lenses, all in the lenses group.
     *
     * @var list<class-string<Lens>>
     */
    private const LENSES = [
        InactiveItemsWithStockLens::class,
        ItemsSoldAtALossLens::class,
        ItemsWithALowMarginLens::class,
        ItemsWithANegativeQuantityLens::class,
        ItemsWithoutSalesLens::class,
        ItemsWithoutSupplierLens::class,
    ];

    /**
     * The reports with their group, their sort, and their icon. The profit and loss report sits with the accounting
     * screens it reads from, before them.
     *
     * @var list<array{report: class-string<Report>, group: string, sort: int, icon: string}>
     */
    private const REPORTS = [
        ['report' => ProfitAndLossReport::class, 'group' => 'accounting', 'sort' => 1, 'icon' => 'o-scale'],
        ['report' => SalesReport::class, 'group' => 'reports', 'sort' => 200, 'icon' => 'o-presentation-chart-line'],
        ['report' => PurchasesReport::class, 'group' => 'reports', 'sort' => 201, 'icon' => 'o-shopping-cart'],
        ['report' => ItemSalesReport::class, 'group' => 'reports', 'sort' => 202, 'icon' => 'o-chart-bar'],
        ['report' => GoldenReport::class, 'group' => 'reports', 'sort' => 203, 'icon' => 'o-trophy'],
    ];

    public function __invoke(): JsonResponse
    {
        $entries = $this->entries()->filter(fn (array $entry): bool => $entry['visible']);

        return response()->json([
            'groups' => $entries
                ->groupBy(fn (array $entry): string => (string) $entry['group'])
                ->map(fn (Collection $items, string $group): array => [
                    'key' => $group === '' ? null : $group,
                    'label' => $group === '' ? null : __("filament/navigation.groups.{$group}"),
                    'items' => $items->sortBy([['sort', 'asc'], ['label', 'asc']])->values()
                        ->map(fn (array $item): array => collect($item)->only(['key', 'label', 'icon', 'path'])->all())->all(),
                ])
                ->sortBy(fn (array $group): int => $group['key'] === null ? -1 : (int) array_search($group['key'], self::GROUPS, true))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Every screen of the sidebar, with whether the signed-in user may open it.
     *
     * @return Collection<int, array{key: string, label: string, group: ?string, icon: string, path: string, sort: int, visible: bool}>
     */
    private function entries(): Collection
    {
        return collect([
            $this->entry('home', __('filament/navigation.home'), null, -2, 'o-home', true, '/'),
            $this->entry('financial-dashboard', FinancialDashboard::label(), null, -1, 'o-banknotes', FinancialDashboard::canAccess()),
            $this->entry('monitoring-dashboard', MonitoringDashboard::label(), null, 0, 'o-shield-exclamation', MonitoringDashboard::canAccess()),
            $this->entry('customer-payments', __('filament/pages/customer_payments.title'), 'sales', 3, 'o-banknotes', Gate::allows('viewAny', CustomerPayment::class)),
            $this->entry('invoice-printing', InvoicePrinting::label(), 'sales', 4, 'o-printer', InvoicePrinting::canAccess()),
            ...array_map(fn (array $screen): array => $this->entry(
                $screen['key'],
                __("filament/resources/{$screen['label']}.navigation_label"),
                $screen['group'],
                $screen['sort'],
                $screen['icon'],
                Gate::allows('viewAny', $screen['model']),
            ), self::MODEL_SCREENS),
            ...array_map(fn (string $lens): array => $this->entry(
                'lenses/'.$lens::uriKey(),
                $lens::label(),
                'lenses',
                100,
                'o-eye',
                $lens::canAccess(),
            ), self::LENSES),
            ...array_map(fn (array $report): array => $this->entry(
                'reports/'.$report['report']::uriKey(),
                $report['report']::label(),
                $report['group'],
                $report['sort'],
                $report['icon'],
                $report['report']::canAccess(),
            ), self::REPORTS),
        ]);
    }

    /**
     * One screen of the sidebar. Its address is its key, unless it is given.
     *
     * @return array{key: string, label: string, group: ?string, icon: string, path: string, sort: int, visible: bool}
     */
    private function entry(string $key, string $label, ?string $group, int $sort, string $icon, bool $visible, ?string $path = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'group' => $group,
            'icon' => $icon,
            'path' => $path ?? "/{$key}",
            'sort' => $sort,
            'visible' => $visible,
        ];
    }
}
