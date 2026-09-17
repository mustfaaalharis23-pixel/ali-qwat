<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\ModelPolicy;
use App\Policies\SupplierPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds SupplierPolicy for the Supplier model, and it inherits ModelPolicy.
     * AR: Laravel يلكي SupplierPolicy لموديل Supplier، وهو يورث ModelPolicy.
     */
    public function test_supplier_uses_supplier_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(Supplier::class);

        $this->assertInstanceOf(SupplierPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: Each ability on suppliers, and the supplier permission it needs.
     * AR: كل ability على الموردين، وصلاحية supplier اللي تحتاجها.
     *
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny needs view' => ['viewAny', 'supplier.view'],
            'view needs view' => ['view', 'supplier.view'],
            'create needs create' => ['create', 'supplier.create'],
            'update needs update' => ['update', 'supplier.update'],
            'delete needs delete' => ['delete', 'supplier.delete'],
            'restore needs restore' => ['restore', 'supplier.restore'],
        ];
    }

    /**
     * EN: A user who has the supplier permission is allowed the ability.
     * AR: اليوزر اللي عنده صلاحية supplier يُسمح له بالـ ability.
     */
    #[DataProvider('abilities')]
    public function test_user_with_the_supplier_permission_is_allowed(string $ability, string $permission): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => $permission,
        ]);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions is denied the ability on suppliers.
     * AR: اليوزر اللي ما عنده صلاحيات يُرفض على الموردين.
     */
    #[DataProvider('abilities')]
    public function test_user_without_the_supplier_permission_is_denied(string $ability, string $permission): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A normal user cannot force delete suppliers, even with all the supplier permissions.
     * AR: اليوزر العادي ما يكدر يحذف الموردين نهائياً، حتى لو عنده كل صلاحيات supplier.
     */
    public function test_normal_user_cannot_force_delete_suppliers(): void
    {
        $user = User::factory()->create();
        foreach (array_column(self::abilities(), 1) as $permission) {
            DB::table('user_permissions')->insertOrIgnore([
                'user_id' => $user->id,
                'permission' => $permission,
            ]);
        }

        $this->assertFalse($user->can('forceDelete', Supplier::factory()->create()));
        $this->assertFalse($user->can('forceDeleteAny', Supplier::class));
    }

    /**
     * EN: A super user can force delete suppliers.
     * AR: is_super يكدر يحذف الموردين نهائياً.
     */
    public function test_super_user_can_force_delete_suppliers(): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertTrue($super->can('forceDelete', Supplier::factory()->create()));
        $this->assertTrue($super->can('forceDeleteAny', Supplier::class));
    }

    /**
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved supplier.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ مورد محفوظ.
     */
    private function target(string $ability): Supplier|string
    {
        return in_array($ability, ['viewAny', 'create'], true) ? Supplier::class : Supplier::factory()->create();
    }
}
