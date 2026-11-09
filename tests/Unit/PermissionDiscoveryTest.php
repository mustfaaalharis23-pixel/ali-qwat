<?php

namespace Tests\Unit;

use App\Admin\Pages\FinancialDashboard;
use App\Models\Audit;
use App\Permissions\PermissionDiscovery;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\BestSellingItemsLens;
use Tests\Fixtures\SalesInvoice;
use Tests\Fixtures\SalesInvoicePolicy;
use Tests\TestCase;

class PermissionDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(SalesInvoice::class, SalesInvoicePolicy::class);
    }

    /**
     * EN: A model with a Policy shows its five basic permissions.
     * AR: الموديل اللي عنده Policy تطلع صلاحياته الخمسة الأساسية.
     */
    public function test_model_with_a_policy_shows_its_five_basic_permissions(): void
    {
        $permissions = (new PermissionDiscovery)->discover([SalesInvoice::class]);

        foreach (['view', 'create', 'update', 'delete', 'restore'] as $ability) {
            $this->assertContains("sales_invoice.{$ability}", $permissions[SalesInvoice::class]);
        }
    }

    /**
     * EN: Public methods written in the Policy show as custom permissions; protected, private, and inherited helper methods do not.
     * AR: الـ public methods المكتوبة بالـ Policy تطلع كصلاحيات خاصة، والـ protected والـ private والمساعدة الموروثة ما يطلعن.
     */
    public function test_only_public_methods_of_the_policy_show_as_custom_permissions(): void
    {
        $permissions = (new PermissionDiscovery)->discover([SalesInvoice::class]);

        $this->assertSame([
            'sales_invoice.view',
            'sales_invoice.create',
            'sales_invoice.update',
            'sales_invoice.delete',
            'sales_invoice.restore',
            'sales_invoice.mark_as_paid',
        ], $permissions[SalesInvoice::class]);
    }

    /**
     * EN: A Policy that sets its basic abilities to view only shows only the view permission.
     * AR: الـ Policy اللي يحدد صلاحياته الأساسية بالعرض فقط، تطلعله بس صلاحية العرض.
     */
    public function test_policy_with_only_the_view_basic_ability_shows_only_the_view_permission(): void
    {
        $permissions = (new PermissionDiscovery)->discover([Audit::class]);

        $this->assertSame(['audit.view'], $permissions[Audit::class]);
    }

    /**
     * EN: Audits show in the app permissions with the view permission only.
     * AR: الـ audits تطلع بصلاحيات النظام بصلاحية العرض فقط.
     */
    public function test_audits_show_in_the_app_permissions_with_view_only(): void
    {
        $permissions = (new PermissionDiscovery)->discoverInApp();

        $this->assertSame(['audit.view'], $permissions[Audit::class]);
    }

    /**
     * EN: The financial dashboard page shows in the app permissions with the view permission and the cashier money transfer permission.
     * AR: صفحة اللوحة المالية تطلع بصلاحيات النظام بصلاحية العرض وصلاحية تحويل أموال الكاشيرية.
     */
    public function test_financial_dashboard_page_shows_in_the_app_permissions_with_view_and_transfer_cashier_money(): void
    {
        $permissions = (new PermissionDiscovery)->discoverInApp();

        $this->assertSame(
            ['financial_dashboard.view', 'financial_dashboard.transfer_cashier_money'],
            $permissions[FinancialDashboard::class],
        );
    }

    /**
     * EN: A lens shows in the lens permissions with one permission built from its uriKey: best-selling-items becomes lenses.best_selling_items.
     * AR: العدسة تطلع بصلاحيات العدسات بصلاحية وحدة مبنية من الـ uriKey مالها: best-selling-items تصير lenses.best_selling_items.
     */
    public function test_lens_shows_in_the_lens_permissions_with_a_permission_from_its_uri_key(): void
    {
        $permissions = (new PermissionDiscovery)->discoverLenses([BestSellingItemsLens::class]);

        $this->assertSame([BestSellingItemsLens::class => 'lenses.best_selling_items'], $permissions);
    }

    /**
     * EN: No two lenses in the app share a uriKey, so no two lenses share an address or a permission.
     * AR: ماكو عدستين بالنظام بنفس الـ uriKey، حتى ماكو عدستين بنفس الرابط أو نفس الصلاحية.
     */
    public function test_every_lens_in_the_app_has_a_unique_uri_key(): void
    {
        $uriKeys = collect((new PermissionDiscovery)->discoverLensesInApp())
            ->keys()
            ->map(fn (string $lens): string => $lens::uriKey());

        $this->assertNotEmpty($uriKeys);
        $this->assertSame($uriKeys->count(), $uriKeys->unique()->count());
    }

    /**
     * EN: Every permission the app discovers, models and pages first, then the lenses, is exactly the list that was stored
     *     before the panel was removed, so no user loses or gains a permission name stored in user_permissions.
     * AR: كل صلاحية يكتشفها النظام، الموديلات والصفحات أول، وبعدين العدسات، هي نفس القائمة اللي كانت محفوظة
     *     قبل ما تنشال اللوحة بالضبط، حتى ماكو يوزر يخسر أو ياخذ اسم صلاحية محفوظ بـ user_permissions.
     */
    public function test_the_discovered_permission_names_are_unchanged(): void
    {
        $discovery = new PermissionDiscovery;

        $this->assertSame([
            'audit.view',
            'batch.view',
            'color.view',
            'color.create',
            'color.update',
            'color.delete',
            'color.restore',
            'container.view',
            'container.create',
            'container.update',
            'container.delete',
            'container.restore',
            'customer.view',
            'customer.create',
            'customer.update',
            'customer.delete',
            'customer.restore',
            'customer_payment.view',
            'customer_payment.create',
            'customer_payment.delete',
            'general_description.view',
            'general_description.create',
            'general_description.update',
            'general_description.delete',
            'general_description.restore',
            'invoice.view',
            'invoice.create',
            'invoice.update',
            'invoice.delete',
            'invoice.restore',
            'invoice.confirm_as_importer',
            'invoice.confirm_as_receiver',
            'invoice.edit_exchange_rate',
            'invoice_category.view',
            'invoice_category.create',
            'invoice_category.update',
            'invoice_category.delete',
            'invoice_category.restore',
            'item.view',
            'item.create',
            'item.update',
            'item.delete',
            'item.restore',
            'item.view_cost_price',
            'lens_power.view',
            'lens_power.create',
            'lens_power.update',
            'lens_power.delete',
            'lens_power.restore',
            'order.view',
            'order.update',
            'payoff.view',
            'payoff.create',
            'payoff.update',
            'payoff.delete',
            'payoff.restore',
            'specific_description.view',
            'specific_description.create',
            'specific_description.update',
            'specific_description.delete',
            'specific_description.restore',
            'supplier.view',
            'supplier.create',
            'supplier.update',
            'supplier.delete',
            'supplier.restore',
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            'user.restore',
            'financial_dashboard.view',
            'financial_dashboard.transfer_cashier_money',
            'invoice_printing.view',
            'monitoring_dashboard.view',
            'lenses.inactive_items_with_stock',
            'lenses.items_sold_at_a_loss',
            'lenses.items_with_a_low_margin',
            'lenses.items_with_a_negative_quantity',
            'lenses.items_without_sales',
            'lenses.items_without_supplier',
        ], [...array_merge(...array_values($discovery->discoverInApp())), ...array_values($discovery->discoverLensesInApp())]);
    }
}
