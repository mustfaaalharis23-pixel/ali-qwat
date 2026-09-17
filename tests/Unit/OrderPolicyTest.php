<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Permissions\PermissionDiscovery;
use App\Policies\ModelPolicy;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds OrderPolicy for the Order model, and it inherits ModelPolicy.
     * AR: Laravel يلكي OrderPolicy لموديل Order، وهو يورث ModelPolicy.
     */
    public function test_order_uses_order_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(Order::class);

        $this->assertInstanceOf(OrderPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: The only order permission that can be given to a user is order.view.
     * AR: الصلاحية الوحيدة للطلبات اللي تنطى لليوزر هي order.view.
     */
    public function test_the_only_order_permission_is_view(): void
    {
        $permissions = (new PermissionDiscovery)->discover([Order::class]);

        $this->assertSame(['order.view'], $permissions[Order::class]);
    }

    /**
     * EN: The abilities that only view orders.
     * AR: الـ abilities اللي بس تعرض الطلبات.
     *
     * @return array<string, array{string}>
     */
    public static function viewAbilities(): array
    {
        return [
            'viewAny' => ['viewAny'],
            'view' => ['view'],
        ];
    }

    /**
     * EN: The abilities that change orders: add, edit, delete, restore, and force delete.
     * AR: الـ abilities اللي تغير الطلبات: إضافة، تعديل، حذف، استرجاع، وحذف نهائي.
     *
     * @return array<string, array{string}>
     */
    public static function changeAbilities(): array
    {
        return [
            'create' => ['create'],
            'update' => ['update'],
            'delete' => ['delete'],
            'deleteAny' => ['deleteAny'],
            'restore' => ['restore'],
            'restoreAny' => ['restoreAny'],
            'forceDelete' => ['forceDelete'],
            'forceDeleteAny' => ['forceDeleteAny'],
        ];
    }

    /**
     * EN: A user who has the order.view permission can view orders.
     * AR: اليوزر اللي عنده صلاحية order.view يكدر يعرض الطلبات.
     */
    #[DataProvider('viewAbilities')]
    public function test_user_with_the_order_view_permission_can_view_orders(string $ability): void
    {
        $user = $this->userWithPermissions(['order.view']);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions cannot view orders.
     * AR: اليوزر اللي ما عنده صلاحيات ما يكدر يعرض الطلبات.
     */
    #[DataProvider('viewAbilities')]
    public function test_user_without_the_order_view_permission_cannot_view_orders(string $ability): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A normal user can never add, edit, delete, or restore orders, even if order permissions other than view are saved for them.
     * AR: اليوزر العادي ما يكدر يضيف أو يعدل أو يحذف أو يسترجع الطلبات أبداً، حتى لو انحفظتله صلاحيات order غير العرض.
     */
    #[DataProvider('changeAbilities')]
    public function test_normal_user_can_never_change_orders(string $ability): void
    {
        $user = $this->userWithPermissions(['order.view', 'order.create', 'order.update', 'order.delete', 'order.restore']);

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A super user can view orders.
     * AR: is_super يكدر يعرض الطلبات.
     */
    #[DataProvider('viewAbilities')]
    public function test_super_user_can_view_orders(string $ability): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertTrue($super->can($ability, $this->target($ability)));
    }

    /**
     * EN: A super user can add, edit, delete, and restore orders.
     * AR: is_super يكدر يضيف ويعدل ويحذف ويسترجع الطلبات.
     */
    #[DataProvider('changeAbilities')]
    public function test_super_user_can_change_orders(string $ability): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertTrue($super->can($ability, $this->target($ability)));
    }

    /**
     * EN: Creates a user and saves the given permissions for them.
     * AR: ينشئ يوزر ويحفظله الصلاحيات المحددة.
     *
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
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
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved order.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ طلب محفوظ.
     */
    private function target(string $ability): Order|string
    {
        if (in_array($ability, ['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny'], true)) {
            return Order::class;
        }

        return Order::factory()->create();
    }
}
