<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\SalesInvoice;
use Tests\Fixtures\SalesInvoicePolicy;
use Tests\TestCase;

class SuperUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(SalesInvoice::class, SalesInvoicePolicy::class);
    }

    /**
     * EN: Every ability on a model: the five basic ones, force delete, and a custom one.
     * AR: كل ability على الموديل: الخمسة الأساسية، والحذف النهائي، وصلاحية خاصة.
     *
     * @return array<string, array{string, bool}>
     */
    public static function abilities(): array
    {
        return [
            'viewAny' => ['viewAny', false],
            'view' => ['view', true],
            'create' => ['create', false],
            'update' => ['update', true],
            'delete' => ['delete', true],
            'deleteAny' => ['deleteAny', false],
            'restore' => ['restore', true],
            'restoreAny' => ['restoreAny', false],
            'forceDelete' => ['forceDelete', true],
            'forceDeleteAny' => ['forceDeleteAny', false],
            'markAsPaid (custom)' => ['markAsPaid', true],
        ];
    }

    /**
     * EN: A super user with no saved permissions is allowed every ability, including force delete and custom ones.
     * AR: يوزر is_super بدون أي صلاحية محفوظة يُسمح له بكل ability، حتى الحذف النهائي والصلاحيات الخاصة.
     */
    #[DataProvider('abilities')]
    public function test_super_user_is_allowed_every_ability_without_any_permission(string $ability, bool $onRecord): void
    {
        $super = User::factory()->create(['is_super' => true]);

        $target = $onRecord ? new SalesInvoice : SalesInvoice::class;

        $this->assertTrue($super->can($ability, $target));
    }
}
