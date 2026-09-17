<?php

namespace Tests\Unit;

use App\Models\Payoff;
use App\Models\User;
use App\Policies\ModelPolicy;
use App\Policies\PayoffPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayoffPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds PayoffPolicy for the Payoff model, and it inherits ModelPolicy.
     * AR: Laravel يلكي PayoffPolicy لموديل Payoff، وهو يورث ModelPolicy.
     */
    public function test_payoff_uses_payoff_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(Payoff::class);

        $this->assertInstanceOf(PayoffPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: Each ability on payoffs, and the payoff permission it needs.
     * AR: كل ability على التسديدات، وصلاحية payoff اللي تحتاجها.
     *
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny needs view' => ['viewAny', 'payoff.view'],
            'view needs view' => ['view', 'payoff.view'],
            'create needs create' => ['create', 'payoff.create'],
            'update needs update' => ['update', 'payoff.update'],
            'delete needs delete' => ['delete', 'payoff.delete'],
            'restore needs restore' => ['restore', 'payoff.restore'],
        ];
    }

    /**
     * EN: A user who has the payoff permission is allowed the ability.
     * AR: اليوزر اللي عنده صلاحية payoff يُسمح له بالـ ability.
     */
    #[DataProvider('abilities')]
    public function test_user_with_the_payoff_permission_is_allowed(string $ability, string $permission): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => $permission,
        ]);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions is denied the ability on payoffs.
     * AR: اليوزر اللي ما عنده صلاحيات يُرفض على التسديدات.
     */
    #[DataProvider('abilities')]
    public function test_user_without_the_payoff_permission_is_denied(string $ability, string $permission): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved payoff.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ تسديد محفوظ.
     */
    private function target(string $ability): Payoff|string
    {
        return in_array($ability, ['viewAny', 'create'], true) ? Payoff::class : Payoff::factory()->create();
    }
}
