<?php

namespace Tests\Unit;

use App\Models\Audit;
use App\Models\BaseModel;
use App\Models\Concerns\Auditable;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * EN: BaseModel is an abstract class that uses the Auditable trait, so any model that extends it records audits.
     * AR: الـ BaseModel كلاس abstract يستخدم الـ trait مال Auditable، فأي مودل يورثه يسجل audits.
     */
    public function test_base_model_is_abstract_and_uses_the_auditable_trait(): void
    {
        $this->assertTrue((new ReflectionClass(BaseModel::class))->isAbstract());
        $this->assertContains(Auditable::class, class_uses_recursive(BaseModel::class));
    }

    /**
     * EN: Item extends BaseModel, and User (which extends Authenticatable) uses the Auditable trait directly.
     * AR: الـ Item يورث BaseModel، والـ User (اللي يورث Authenticatable) يستخدم الـ trait مال Auditable مباشرة.
     */
    public function test_item_extends_base_model_and_user_uses_the_auditable_trait(): void
    {
        $this->assertTrue(is_subclass_of(Item::class, BaseModel::class));
        $this->assertContains(Auditable::class, class_uses_recursive(User::class));
    }

    /**
     * EN: Creating a model records a "created" audit with a full copy of the record.
     * AR: إنشاء مودل يسجل audit نوعه "created" وبيه نسخة كاملة من السجل.
     */
    public function test_creating_a_model_records_a_full_copy_of_the_record(): void
    {
        $item = Item::factory()->create(['name' => 'Rice']);

        $audit = $this->auditFor($item, 'created');

        $this->assertNotNull($audit);
        $this->assertEqualsCanonicalizing(
            [
                'id', 'name', 'image', 'description', 'is_active', 'supplier_id',
                'sale_price', 'pieces_per_packet', 'cost_price', 'last_cost_price', 'available_quantity', 'incoming_quantity',
                'created_at', 'updated_at', 'deleted_at',
            ],
            array_keys($audit->attribute_changes['attributes']),
        );
        $this->assertSame('Rice', $audit->attribute_changes['attributes']['name']);
    }

    /**
     * EN: Updating a model records an "updated" audit with the full record before and after, including the fields that did not change.
     * AR: تعديل مودل يسجل audit نوعه "updated" وبيه السجل كامل قبل وبعد، حتى الحقول اللي ما تغيرت.
     */
    public function test_updating_a_model_records_the_full_record_before_and_after(): void
    {
        $item = Item::factory()->create(['name' => 'Rice', 'image' => 'items/rice.jpg']);

        $item->update(['name' => 'White rice']);

        $audit = $this->auditFor($item, 'updated');

        $this->assertNotNull($audit);
        $this->assertSame('Rice', $audit->attribute_changes['old']['name']);
        $this->assertSame('White rice', $audit->attribute_changes['attributes']['name']);
        $this->assertSame('items/rice.jpg', $audit->attribute_changes['old']['image']);
        $this->assertSame('items/rice.jpg', $audit->attribute_changes['attributes']['image']);
    }

    /**
     * EN: Soft deleting a model records a "deleted" audit with a full copy of the record.
     * AR: الحذف الـ soft delete للمودل يسجل audit نوعه "deleted" وبيه نسخة كاملة من السجل.
     */
    public function test_deleting_a_model_records_a_deleted_audit(): void
    {
        $item = Item::factory()->create(['name' => 'Rice']);

        $item->delete();

        $audit = $this->auditFor($item, 'deleted');

        $this->assertNotNull($audit);
        $this->assertSame('Rice', $audit->attribute_changes['old']['name']);
    }

    /**
     * EN: Restoring a soft deleted model records a "restored" audit.
     * AR: استرجاع مودل محذوف soft delete يسجل audit نوعه "restored".
     */
    public function test_restoring_a_model_records_a_restored_audit(): void
    {
        $item = Item::factory()->create();
        $item->delete();

        $item->restore();

        $this->assertNotNull($this->auditFor($item, 'restored'));
    }

    /**
     * EN: Force deleting a model records a "forceDeleted" audit.
     * AR: الحذف النهائي للمودل يسجل audit نوعه "forceDeleted".
     */
    public function test_force_deleting_a_model_records_a_force_deleted_audit(): void
    {
        $item = Item::factory()->create();

        $item->forceDelete();

        $this->assertNotNull($this->auditFor($item, 'forceDeleted'));
    }

    /**
     * EN: The audit saves the logged-in user who made the change.
     * AR: الـ audit يحفظ اليوزر المسجل دخول اللي سوى التغيير.
     */
    public function test_audit_saves_the_logged_in_user_who_made_the_change(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = Item::factory()->create();

        $audit = $this->auditFor($item, 'created');

        $this->assertSame($user->getMorphClass(), $audit->causer_type);
        $this->assertEquals($user->id, $audit->causer_id);
    }

    /**
     * EN: When no user is logged in (such as a seeder or a command), the audit is saved without a user.
     * AR: إذا ماكو يوزر مسجل دخول (مثل seeder أو command)، الـ audit ينحفظ بدون يوزر.
     */
    public function test_audit_is_saved_without_a_user_when_no_one_is_logged_in(): void
    {
        $item = Item::factory()->create();

        $audit = $this->auditFor($item, 'created');

        $this->assertNotNull($audit);
        $this->assertNull($audit->causer_id);
        $this->assertNull($audit->causer_type);
    }

    /**
     * EN: Creating a user records a "created" audit without the hidden fields (password and remember_token).
     * AR: إنشاء يوزر يسجل audit نوعه "created" بدون الحقول المخفية (password و remember_token).
     */
    public function test_creating_a_user_records_an_audit_without_hidden_fields(): void
    {
        $user = User::factory()->create(['name' => 'Ali']);

        $audit = $this->auditFor($user, 'created');

        $this->assertNotNull($audit);
        $this->assertSame('Ali', $audit->attribute_changes['attributes']['name']);
        $this->assertArrayNotHasKey('password', $audit->attribute_changes['attributes']);
        $this->assertArrayNotHasKey('remember_token', $audit->attribute_changes['attributes']);
    }

    /**
     * EN: Updating a user records an "updated" audit without the hidden fields, before or after.
     * AR: تعديل يوزر يسجل audit نوعه "updated" بدون الحقول المخفية، لا قبل ولا بعد.
     */
    public function test_updating_a_user_records_an_audit_without_hidden_fields(): void
    {
        $user = User::factory()->create(['name' => 'Ali']);

        $user->update(['name' => 'Ali Basim', 'password' => 'new-password']);

        $audit = $this->auditFor($user, 'updated');

        $this->assertNotNull($audit);
        $this->assertSame('Ali', $audit->attribute_changes['old']['name']);
        $this->assertSame('Ali Basim', $audit->attribute_changes['attributes']['name']);
        $this->assertArrayNotHasKey('password', $audit->attribute_changes['old']);
        $this->assertArrayNotHasKey('password', $audit->attribute_changes['attributes']);
        $this->assertArrayNotHasKey('remember_token', $audit->attribute_changes['old']);
        $this->assertArrayNotHasKey('remember_token', $audit->attribute_changes['attributes']);
    }

    /**
     * EN: Audits are saved with the App\Models\Audit model, and each model reaches its own audits through it.
     * AR: الـ audits تنحفظ بموديل App\Models\Audit، وكل مودل يوصل للـ audits مالته من خلاله.
     */
    public function test_audits_are_saved_with_the_audit_model(): void
    {
        $item = Item::factory()->create();

        $this->assertSame(Audit::class, config('activitylog.activity_model'));
        $this->assertInstanceOf(Audit::class, $item->activitiesAsSubject()->first());
    }

    /**
     * EN: The Audit model does not extend BaseModel, so saving an audit never records an audit of itself.
     * AR: موديل Audit ما يورث BaseModel، فحفظ الـ audit ما يسجل audit على نفسه أبداً.
     */
    public function test_saving_an_audit_does_not_record_an_audit_of_itself(): void
    {
        Item::factory()->create();

        $this->assertFalse(is_subclass_of(Audit::class, BaseModel::class));
        $this->assertSame(1, Audit::query()->count());
    }

    /**
     * EN: Finds the latest audit of the given event for the given model.
     * AR: يجيب آخر audit من نوع الحدث المحدد للمودل المحدد.
     */
    private function auditFor(Model $model, string $event): ?Activity
    {
        return Activity::query()
            ->forSubject($model)
            ->forEvent($event)
            ->latest('id')
            ->first();
    }
}
