<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\User;
use App\Policies\ItemPolicy;
use App\Policies\ModelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ItemPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds ItemPolicy for the Item model, and it inherits ModelPolicy.
     * AR: Laravel يلكي ItemPolicy لموديل Item، وهو يورث ModelPolicy.
     */
    public function test_item_uses_item_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(Item::class);

        $this->assertInstanceOf(ItemPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: Each ability on items, and the item permission it needs.
     * AR: كل ability على الأصناف، وصلاحية item اللي تحتاجها.
     *
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny needs view' => ['viewAny', 'item.view'],
            'view needs view' => ['view', 'item.view'],
            'create needs create' => ['create', 'item.create'],
            'update needs update' => ['update', 'item.update'],
            'delete needs delete' => ['delete', 'item.delete'],
            'restore needs restore' => ['restore', 'item.restore'],
        ];
    }

    /**
     * EN: A user who has the item permission is allowed the ability.
     * AR: اليوزر اللي عنده صلاحية item يُسمح له بالـ ability.
     */
    #[DataProvider('abilities')]
    public function test_user_with_the_item_permission_is_allowed(string $ability, string $permission): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => $permission,
        ]);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions is denied the ability on items.
     * AR: اليوزر اللي ما عنده صلاحيات يُرفض على الأصناف.
     */
    #[DataProvider('abilities')]
    public function test_user_without_the_item_permission_is_denied(string $ability, string $permission): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A super user can force delete items, unlike users.
     * AR: is_super يكدر يحذف الأصناف نهائياً، مو مثل اليوزرز.
     */
    public function test_super_user_can_force_delete_items(): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertTrue($super->can('forceDelete', Item::factory()->create()));
        $this->assertTrue($super->can('forceDeleteAny', Item::class));
    }

    /**
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved item.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ صنف محفوظ.
     */
    private function target(string $ability): Item|string
    {
        return in_array($ability, ['viewAny', 'create'], true) ? Item::class : Item::factory()->create();
    }
}
