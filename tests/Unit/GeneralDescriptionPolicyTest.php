<?php

namespace Tests\Unit;

use App\Models\GeneralDescription;
use App\Models\User;
use App\Policies\GeneralDescriptionPolicy;
use App\Policies\ModelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeneralDescriptionPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Laravel finds GeneralDescriptionPolicy for the GeneralDescription model, and it inherits ModelPolicy.
     * AR: Laravel يلكي GeneralDescriptionPolicy لموديل GeneralDescription، وهو يورث ModelPolicy.
     */
    public function test_general_description_uses_its_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(GeneralDescription::class);

        $this->assertInstanceOf(GeneralDescriptionPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: Each ability on general descriptions, and the general_description permission it needs.
     * AR: كل ability على الوصوف العامة، وصلاحية general_description اللي تحتاجها.
     *
     * @return array<string, array{string, string}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny needs view' => ['viewAny', 'general_description.view'],
            'view needs view' => ['view', 'general_description.view'],
            'create needs create' => ['create', 'general_description.create'],
            'update needs update' => ['update', 'general_description.update'],
            'delete needs delete' => ['delete', 'general_description.delete'],
            'restore needs restore' => ['restore', 'general_description.restore'],
        ];
    }

    /**
     * EN: A user who has the general_description permission is allowed the ability.
     * AR: اليوزر اللي عنده صلاحية general_description يُسمح له بالـ ability.
     */
    #[DataProvider('abilities')]
    public function test_user_with_the_general_description_permission_is_allowed(string $ability, string $permission): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => $permission,
        ]);

        $this->assertTrue($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: A user who has no permissions is denied the ability on general descriptions.
     * AR: اليوزر اللي ما عنده صلاحيات يُرفض على الوصوف العامة.
     */
    #[DataProvider('abilities')]
    public function test_user_without_the_general_description_permission_is_denied(string $ability, string $permission): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can($ability, $this->target($ability)));
    }

    /**
     * EN: Abilities on the whole model get the class name; abilities on one record get a saved general description.
     * AR: الـ abilities على الموديل كله تاخذ اسم الـ class، واللي على سجل واحد تاخذ وصف عام محفوظ.
     */
    private function target(string $ability): GeneralDescription|string
    {
        return in_array($ability, ['viewAny', 'create'], true) ? GeneralDescription::class : GeneralDescription::factory()->create();
    }
}
