<?php

namespace Tests\Unit;

use App\Models\Audit;
use App\Models\Item;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Policies\ModelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds AuditPolicy for the Audit model, and it inherits ModelPolicy.
     * AR: Laravel يلكي AuditPolicy لموديل Audit، وهو يورث ModelPolicy.
     */
    public function test_audit_uses_audit_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(Audit::class);

        $this->assertInstanceOf(AuditPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: The abilities that only view audits.
     * AR: الـ abilities اللي بس تعرض الـ audits.
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
     * EN: The abilities that change audits: add, edit, delete, restore, and force delete.
     * AR: الـ abilities اللي تغير الـ audits: إضافة، تعديل، حذف، استرجاع، وحذف نهائي.
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
     * EN: A user who has the audit.view permission can view audits.
     * AR: اليوزر اللي عنده صلاحية audit.view يكدر يعرض الـ audits.
     */
    #[DataProvider('viewAbilities')]
    public function test_user_with_the_audit_view_permission_can_view_audits(string $ability): void
    {
        $user = $this->userWithPermissions(['audit.view']);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions cannot view audits.
     * AR: اليوزر اللي ما عنده صلاحيات ما يكدر يعرض الـ audits.
     */
    #[DataProvider('viewAbilities')]
    public function test_user_without_the_audit_view_permission_cannot_view_audits(string $ability): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A normal user can never change audits, even if audit permissions other than view are saved for them.
     * AR: اليوزر العادي ما يكدر يغير الـ audits أبداً، حتى لو انحفظتله صلاحيات audit غير العرض.
     */
    #[DataProvider('changeAbilities')]
    public function test_normal_user_can_never_change_audits(string $ability): void
    {
        $user = $this->userWithPermissions(['audit.view', 'audit.create', 'audit.update', 'audit.delete', 'audit.restore']);

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A super user can view audits.
     * AR: is_super يكدر يعرض الـ audits.
     */
    #[DataProvider('viewAbilities')]
    public function test_super_user_can_view_audits(string $ability): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertTrue($super->can($ability, $this->target($ability)));
    }

    /**
     * EN: A super user cannot change audits either: audits are read only for everyone.
     * AR: حتى is_super ما يكدر يغير الـ audits: الـ audits للقراءة فقط للكل.
     */
    #[DataProvider('changeAbilities')]
    public function test_super_user_cannot_change_audits(string $ability): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $this->assertFalse($super->can($ability, $this->target($ability)));
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
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved audit.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ audit محفوظ.
     */
    private function target(string $ability): Audit|string
    {
        if (in_array($ability, ['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny'], true)) {
            return Audit::class;
        }

        Item::factory()->create();

        return Audit::query()->latest('id')->firstOrFail();
    }
}
