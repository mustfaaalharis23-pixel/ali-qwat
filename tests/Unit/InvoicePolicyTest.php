<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\User;
use App\Permissions\PermissionDiscovery;
use App\Policies\InvoicePolicy;
use App\Policies\ModelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds InvoicePolicy for the Invoice model, and it inherits ModelPolicy.
     * AR: Laravel يلكي InvoicePolicy لموديل Invoice، وهو يورث ModelPolicy.
     */
    public function test_invoice_uses_invoice_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(Invoice::class);

        $this->assertInstanceOf(InvoicePolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: The invoice permissions are the five basic ones and a confirm permission for each side, without update and delete counted twice.
     * AR: صلاحيات الفاتورة هي الخمسة الأساسية ويا صلاحية تأكيد لكل جهة، بدون ما تنحسب update و delete مرتين.
     */
    public function test_invoice_permissions_are_the_basic_ones_and_a_confirm_for_each_side(): void
    {
        $permissions = (new PermissionDiscovery)->discover([Invoice::class]);

        $this->assertSame([
            'invoice.view',
            'invoice.create',
            'invoice.update',
            'invoice.delete',
            'invoice.restore',
            'invoice.confirm_as_importer',
            'invoice.confirm_as_receiver',
        ], $permissions[Invoice::class]);
    }

    /**
     * EN: Each ability on invoices without extra conditions, and the invoice permission it needs.
     * AR: كل ability على الفواتير بدون شروط إضافية، وصلاحية invoice اللي تحتاجها.
     *
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny needs view' => ['viewAny', 'invoice.view'],
            'view needs view' => ['view', 'invoice.view'],
            'create needs create' => ['create', 'invoice.create'],
            'restore needs restore' => ['restore', 'invoice.restore'],
        ];
    }

    /**
     * EN: A user who has the invoice permission is allowed the ability.
     * AR: اليوزر اللي عنده صلاحية invoice يُسمح له بالـ ability.
     */
    #[DataProvider('abilities')]
    public function test_user_with_the_invoice_permission_is_allowed(string $ability, string $permission): void
    {
        $user = $this->userWith($permission);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions is denied the ability on invoices.
     * AR: اليوزر اللي ما عنده صلاحيات يُرفض على الفواتير.
     */
    #[DataProvider('abilities')]
    public function test_user_without_the_invoice_permission_is_denied(string $ability, string $permission): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A normal user cannot update a general invoice, even with the update permission.
     * AR: اليوزر العادي ما يكدر يعدل فاتورة عامة، حتى لو عنده صلاحية التعديل.
     */
    public function test_normal_user_cannot_update_a_general_invoice(): void
    {
        $this->assertFalse($this->userWith('invoice.update')->can('update', Invoice::factory()->create()));
    }

    /**
     * EN: A super user can update a general invoice.
     * AR: is_super يكدر يعدل فاتورة عامة.
     */
    public function test_super_user_can_update_a_general_invoice(): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertTrue($super->can('update', Invoice::factory()->create()));
    }

    /**
     * EN: A user with the update permission can update a pending purchase invoice within 24 hours of creating it.
     * AR: اليوزر اللي عنده صلاحية التعديل يكدر يعدل فاتورة شراء معلّقة خلال 24 ساعة من إنشائها.
     */
    public function test_user_can_update_a_pending_purchase_invoice_within_24_hours(): void
    {
        $invoice = Invoice::factory()->purchase()->create(['created_at' => now()->subHours(23)]);

        $this->assertTrue($this->userWith('invoice.update')->can('update', $invoice));
    }

    /**
     * EN: A user cannot update a purchase invoice more than 24 hours after creating it.
     * AR: اليوزر ما يكدر يعدل فاتورة شراء بعد 24 ساعة من إنشائها.
     */
    public function test_user_cannot_update_a_purchase_invoice_after_24_hours(): void
    {
        $invoice = Invoice::factory()->purchase()->create(['created_at' => now()->subHours(25)]);

        $this->assertFalse($this->userWith('invoice.update')->can('update', $invoice));
    }

    /**
     * EN: A user can still update a purchase invoice the importer confirmed, within 24 hours: it is locked only by the receiver's confirmation.
     * AR: اليوزر بعده يكدر يعدل فاتورة شراء أكدها المستورد، خلال 24 ساعة: تنقفل بتأكيد المستلم بس.
     */
    public function test_user_can_update_a_purchase_invoice_the_importer_confirmed(): void
    {
        $invoice = Invoice::factory()->purchase()->importerConfirmed()->create(['created_at' => now()->subHours(23)]);

        $this->assertTrue($this->userWith('invoice.update')->can('update', $invoice));
    }

    /**
     * EN: A user cannot update a confirmed purchase invoice.
     * AR: اليوزر ما يكدر يعدل فاتورة شراء مؤكدة.
     */
    public function test_user_cannot_update_a_confirmed_purchase_invoice(): void
    {
        $invoice = Invoice::factory()->purchase()->confirmed()->create();

        $this->assertFalse($this->userWith('invoice.update')->can('update', $invoice));
    }

    /**
     * EN: A user without the update permission cannot update a pending purchase invoice.
     * AR: اليوزر اللي ما عنده صلاحية التعديل ما يكدر يعدل فاتورة شراء معلّقة.
     */
    public function test_user_without_the_update_permission_cannot_update_a_purchase_invoice(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $this->assertFalse(User::factory()->create()->can('update', $invoice));
    }

    /**
     * EN: A user with the delete permission can delete a general invoice within 24 hours of creating it.
     * AR: اليوزر اللي عنده صلاحية الحذف يكدر يحذف فاتورة عامة خلال 24 ساعة من إنشائها.
     */
    public function test_user_can_delete_a_general_invoice_within_24_hours(): void
    {
        $invoice = Invoice::factory()->create(['created_at' => now()->subHours(23)]);

        $this->assertTrue($this->userWith('invoice.delete')->can('delete', $invoice));
    }

    /**
     * EN: A user cannot delete a general invoice more than 24 hours after creating it.
     * AR: اليوزر ما يكدر يحذف فاتورة عامة بعد 24 ساعة من إنشائها.
     */
    public function test_user_cannot_delete_a_general_invoice_after_24_hours(): void
    {
        $invoice = Invoice::factory()->create(['created_at' => now()->subHours(25)]);

        $this->assertFalse($this->userWith('invoice.delete')->can('delete', $invoice));
    }

    /**
     * EN: A user without the delete permission cannot delete a general invoice.
     * AR: اليوزر اللي ما عنده صلاحية الحذف ما يكدر يحذف فاتورة عامة.
     */
    public function test_user_without_the_delete_permission_cannot_delete_a_general_invoice(): void
    {
        $this->assertFalse(User::factory()->create()->can('delete', Invoice::factory()->create()));
    }

    /**
     * EN: A normal user cannot delete a purchase invoice, even with the delete permission.
     * AR: اليوزر العادي ما يكدر يحذف فاتورة شراء، حتى لو عنده صلاحية الحذف.
     */
    public function test_normal_user_cannot_delete_a_purchase_invoice(): void
    {
        $this->assertFalse($this->userWith('invoice.delete')->can('delete', Invoice::factory()->purchase()->create()));
    }

    /**
     * EN: A super user can delete a purchase invoice.
     * AR: is_super يكدر يحذف فاتورة شراء.
     */
    public function test_super_user_can_delete_a_purchase_invoice(): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertTrue($super->can('delete', Invoice::factory()->purchase()->create()));
    }

    /**
     * EN: The importer's permission confirms a pending purchase invoice, and no user without it does.
     * AR: صلاحية المستورد تأكد فاتورة شراء معلّقة، واللي ما عنده الصلاحية ما يأكد.
     */
    public function test_importer_permission_confirms_a_pending_purchase_invoice(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $this->assertTrue($this->userWith('invoice.confirm_as_importer')->can('confirmAsImporter', $invoice));
        $this->assertFalse(User::factory()->create()->can('confirmAsImporter', $invoice));
    }

    /**
     * EN: The receiver's permission confirms a purchase invoice the importer confirmed, but not a pending one.
     * AR: صلاحية المستلم تأكد فاتورة شراء أكدها المستورد، بس مو المعلّقة.
     */
    public function test_receiver_permission_confirms_an_invoice_the_importer_confirmed(): void
    {
        $user = $this->userWith('invoice.confirm_as_receiver');

        $this->assertTrue($user->can('confirmAsReceiver', Invoice::factory()->purchase()->importerConfirmed()->create()));
        $this->assertFalse($user->can('confirmAsReceiver', Invoice::factory()->purchase()->create()));
    }

    /**
     * EN: Creates a normal user with the given permission.
     * AR: ينشئ يوزر عادي عنده الصلاحية المحددة.
     */
    private function userWith(string $permission): User
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => $permission,
        ]);

        return $user;
    }

    /**
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved invoice.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ فاتورة محفوظة.
     */
    private function target(string $ability): Invoice|string
    {
        return in_array($ability, ['viewAny', 'create'], true) ? Invoice::class : Invoice::factory()->create();
    }
}
