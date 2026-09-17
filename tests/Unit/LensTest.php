<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\BestSellingItemsLens;
use Tests\TestCase;

class LensTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A user with the lens permission, built from its uriKey (lenses.best_selling_items), can open the lens.
     * AR: اليوزر اللي عنده صلاحية العدسة، المبنية من الـ uriKey مالها (lenses.best_selling_items)، يكدر يفتح العدسة.
     */
    public function test_user_with_the_lens_permission_can_open_the_lens(): void
    {
        $this->actingAs($this->userWithPermission('lenses.best_selling_items'));

        $this->assertTrue(BestSellingItemsLens::canAccess());
    }

    /**
     * EN: A user without any permission cannot open the lens.
     * AR: اليوزر اللي ما عنده أي صلاحية ما يكدر يفتح العدسة.
     */
    public function test_user_without_the_lens_permission_cannot_open_the_lens(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        $this->assertFalse(BestSellingItemsLens::canAccess());
    }

    /**
     * EN: The permission of another lens does not open this lens: each lens has its own permission.
     * AR: صلاحية عدسة ثانية ما تفتح هذي العدسة: كل عدسة الها صلاحية خاصة بيها.
     */
    public function test_permission_of_another_lens_does_not_open_this_lens(): void
    {
        $this->actingAs($this->userWithPermission('lenses.items_without_supplier'));

        $this->assertFalse(BestSellingItemsLens::canAccess());
    }

    /**
     * EN: A super user can open the lens without the permission.
     * AR: السوبر يوزر يكدر يفتح العدسة بدون الصلاحية.
     */
    public function test_super_user_can_open_the_lens_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'is_super' => true]));

        $this->assertTrue(BestSellingItemsLens::canAccess());
    }

    /**
     * EN: The lens address is built from its uriKey under lenses: lenses/best-selling-items.
     * AR: رابط العدسة مبني من الـ uriKey مالها تحت lenses: lenses/best-selling-items.
     */
    public function test_lens_address_is_built_from_its_uri_key(): void
    {
        $this->assertSame('lenses/best-selling-items', BestSellingItemsLens::getSlug());
    }

    /**
     * EN: The name of the sidebar group of every lens in each language.
     * AR: اسم مجموعة القائمة الجانبية لكل العدسات بكل لغة.
     *
     * @return array<string, array{string, string}>
     */
    public static function groupLabels(): array
    {
        return [
            'Arabic' => ['ar', 'العدسات'],
            'English' => ['en', 'Lenses'],
        ];
    }

    /**
     * EN: Every lens is listed under the Lenses sidebar group, named in the current language.
     * AR: كل عدسة تنعرض تحت مجموعة العدسات بالقائمة الجانبية، باسمها باللغة الحالية.
     */
    #[DataProvider('groupLabels')]
    public function test_lens_is_listed_under_the_lenses_group_in_the_current_language(string $locale, string $group): void
    {
        App::setLocale($locale);

        $this->assertSame($group, BestSellingItemsLens::getNavigationGroup());
    }

    /**
     * EN: Creates an active normal user with the given permission.
     * AR: ينشئ يوزر عادي فعّال عنده الصلاحية المعطاة.
     */
    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create(['is_active' => true]);
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);

        return $user;
    }
}
