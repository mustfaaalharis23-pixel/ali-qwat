<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\SalesInvoice;
use Tests\Fixtures\SalesInvoicePolicy;
use Tests\TestCase;

class ModelPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(SalesInvoice::class, SalesInvoicePolicy::class);
    }

    /**
     * EN: Each ability Filament calls, and the permission it needs.
     * AR: كل ability يناديها Filament، والصلاحية اللي تحتاجها.
     *
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny needs view' => ['viewAny', 'sales_invoice.view'],
            'view needs view' => ['view', 'sales_invoice.view'],
            'create needs create' => ['create', 'sales_invoice.create'],
            'update needs update' => ['update', 'sales_invoice.update'],
            'delete needs delete' => ['delete', 'sales_invoice.delete'],
            'deleteAny needs delete' => ['deleteAny', 'sales_invoice.delete'],
            'restore needs restore' => ['restore', 'sales_invoice.restore'],
            'restoreAny needs restore' => ['restoreAny', 'sales_invoice.restore'],
        ];
    }

    /**
     * EN: A user who has the permission is allowed the ability.
     * AR: اليوزر اللي عنده الصلاحية يُسمح له بالـ ability.
     */
    #[DataProvider('abilities')]
    public function test_user_with_the_permission_is_allowed(string $ability, string $permission): void
    {
        $user = $this->userWithPermissions($permission);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions is denied the ability.
     * AR: اليوزر اللي ما عنده صلاحيات يُرفض.
     */
    #[DataProvider('abilities')]
    public function test_user_without_the_permission_is_denied(string $ability, string $permission): void
    {
        $user = $this->userWithPermissions();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A permission for one ability does not allow another: a user with only view cannot create, update, delete or restore.
     * AR: صلاحية ability وحدة ما تسمح بغيرها: اليوزر اللي عنده view بس ما يكدر يضيف ولا يعدل ولا يحذف ولا يسترجع.
     */
    public function test_permission_for_one_ability_does_not_allow_another(): void
    {
        $user = $this->userWithPermissions('sales_invoice.view');

        $this->assertFalse($user->can('create', SalesInvoice::class));
        $this->assertFalse($user->can('update', new SalesInvoice));
        $this->assertFalse($user->can('delete', new SalesInvoice));
        $this->assertFalse($user->can('restore', new SalesInvoice));
    }

    /**
     * EN: A permission for another model does not apply: invoice.view does not allow viewing a sales invoice.
     * AR: صلاحية موديل ثاني ما تنطبق: invoice.view ما تسمح بعرض sales invoice.
     */
    public function test_permission_for_another_model_does_not_apply(): void
    {
        $user = $this->userWithPermissions('invoice.view');

        $this->assertFalse($user->can('view', new SalesInvoice));
    }

    /**
     * EN: Force delete is always denied for a normal user, even if a force_delete permission is saved for them.
     * AR: الحذف النهائي مرفوض دائماً لليوزر العادي، حتى لو محفوظة إله صلاحية force_delete.
     */
    public function test_force_delete_is_denied_even_with_a_force_delete_permission(): void
    {
        $user = $this->userWithPermissions('sales_invoice.force_delete');

        $this->assertFalse($user->can('forceDelete', new SalesInvoice));
        $this->assertFalse($user->can('forceDeleteAny', SalesInvoice::class));
    }

    /**
     * EN: A user who has a custom permission (sales_invoice.mark_as_paid) is allowed its ability (markAsPaid).
     * AR: اليوزر اللي عنده صلاحية خاصة (sales_invoice.mark_as_paid) يُسمح له بالـ ability مالتها (markAsPaid).
     */
    public function test_user_with_a_custom_permission_is_allowed(): void
    {
        $user = $this->userWithPermissions('sales_invoice.mark_as_paid');

        $this->assertTrue($user->can('markAsPaid', new SalesInvoice));
    }

    /**
     * EN: A user without the custom permission is denied its ability.
     * AR: اليوزر اللي ما عنده الصلاحية الخاصة يُرفض.
     */
    public function test_user_without_a_custom_permission_is_denied(): void
    {
        $user = $this->userWithPermissions();

        $this->assertFalse($user->can('markAsPaid', new SalesInvoice));
    }

    /**
     * EN: Creates a normal user and saves the given permissions for them.
     * AR: ينشئ يوزر عادي ويحفظله الصلاحيات المعطاة.
     */
    private function userWithPermissions(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert([
                'user_id' => $user->id,
                'permission' => $permission,
            ]);
        }

        return $user;
    }

    /**
     * EN: Abilities on the whole model get the class name; abilities on one record get a record.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ سجل.
     */
    private function target(string $ability): SalesInvoice|string
    {
        $modelAbilities = ['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny'];

        return in_array($ability, $modelAbilities, true) ? SalesInvoice::class : new SalesInvoice;
    }
}
