<?php

namespace Tests\Unit;

use App\Models\SpecificDescription;
use App\Models\User;
use App\Policies\ModelPolicy;
use App\Policies\SpecificDescriptionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpecificDescriptionPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds SpecificDescriptionPolicy for the SpecificDescription model, and it inherits ModelPolicy.
     * AR: Laravel يلكي SpecificDescriptionPolicy لموديل SpecificDescription، وهو يورث ModelPolicy.
     */
    public function test_specific_description_uses_its_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(SpecificDescription::class);

        $this->assertInstanceOf(SpecificDescriptionPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: Each ability on specific descriptions, and the specific_description permission it needs.
     * AR: كل ability على الوصوف الخاصة، وصلاحية specific_description اللي تحتاجها.
     *
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny needs view' => ['viewAny', 'specific_description.view'],
            'view needs view' => ['view', 'specific_description.view'],
            'create needs create' => ['create', 'specific_description.create'],
            'update needs update' => ['update', 'specific_description.update'],
            'delete needs delete' => ['delete', 'specific_description.delete'],
            'restore needs restore' => ['restore', 'specific_description.restore'],
        ];
    }

    /**
     * EN: A user who has the specific_description permission is allowed the ability.
     * AR: اليوزر اللي عنده صلاحية specific_description يُسمح له بالـ ability.
     */
    #[DataProvider('abilities')]
    public function test_user_with_the_specific_description_permission_is_allowed(string $ability, string $permission): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => $permission,
        ]);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions is denied the ability on specific descriptions.
     * AR: اليوزر اللي ما عنده صلاحيات يُرفض على الوصوف الخاصة.
     */
    #[DataProvider('abilities')]
    public function test_user_without_the_specific_description_permission_is_denied(string $ability, string $permission): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved specific description.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ وصف خاص محفوظ.
     */
    private function target(string $ability): SpecificDescription|string
    {
        return in_array($ability, ['viewAny', 'create'], true) ? SpecificDescription::class : SpecificDescription::factory()->create();
    }
}
