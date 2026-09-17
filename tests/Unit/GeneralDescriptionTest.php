<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Models\GeneralDescription;
use App\Models\SpecificDescription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeneralDescriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The database starts with one purchases general description of type output, and GeneralDescription::purchases() returns it.
     * AR: الداتابيس تبدي بوصف عام واحد للشراء نوعه خارج، و GeneralDescription::purchases() يرجعه.
     */
    public function test_database_starts_with_one_purchases_description(): void
    {
        $this->assertSame(1, GeneralDescription::query()->where('is_purchases_description', true)->count());

        $purchases = GeneralDescription::purchases();

        $this->assertTrue($purchases->is_purchases_description);
        $this->assertSame(InvoiceType::Output, $purchases->type);
    }

    /**
     * EN: The required columns: name and type.
     * AR: الأعمدة الإجبارية: الاسم والنوع.
     *
     * @return array<string, array{string}>
     */
    public static function requiredColumns(): array
    {
        return [
            'name' => ['name'],
            'type' => ['type'],
        ];
    }

    /**
     * EN: A general description cannot be saved in the database without a name or without a type.
     * AR: الوصف العام ما ينحفظ بالداتابيس بدون اسم أو بدون نوع.
     */
    #[DataProvider('requiredColumns')]
    public function test_general_description_cannot_be_saved_without_a_required_column(string $column): void
    {
        $row = ['name' => 'Salaries', 'type' => 'output'];
        unset($row[$column]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23502]');

        DB::table('general_descriptions')->insert($row);
    }

    /**
     * EN: A new general description saved without is_purchases_description is not a purchases description.
     * AR: الوصف العام الجديد اللي ينحفظ بدون is_purchases_description ما يكون وصف شراء.
     */
    public function test_new_general_description_is_not_a_purchases_description_by_default(): void
    {
        DB::table('general_descriptions')->insert([
            'name' => 'Salaries',
            'type' => 'output',
        ]);

        $this->assertDatabaseHas('general_descriptions', [
            'name' => 'Salaries',
            'is_purchases_description' => false,
        ]);
    }

    /**
     * EN: The two types: input and output.
     * AR: النوعين: داخل وخارج.
     *
     * @return array<string, array{InvoiceType, string}>
     */
    public static function types(): array
    {
        return [
            'input' => [InvoiceType::Input, 'input'],
            'output' => [InvoiceType::Output, 'output'],
        ];
    }

    /**
     * EN: The type is stored in the database as its value, and read back from the model as the enum.
     * AR: النوع ينحفظ بالداتابيس كقيمته، ويرجع من الموديل كـ enum.
     */
    #[DataProvider('types')]
    public function test_type_is_stored_as_its_value_and_read_as_the_enum(InvoiceType $type, string $value): void
    {
        $description = GeneralDescription::factory()->create(['type' => $type]);

        $this->assertDatabaseHas('general_descriptions', [
            'id' => $description->id,
            'type' => $value,
        ]);
        $this->assertSame($type, $description->fresh()->type);
    }

    /**
     * EN: A general description reaches the specific descriptions under it.
     * AR: الوصف العام يوصل للوصوف الخاصة اللي تحته.
     */
    public function test_general_description_has_its_specific_descriptions(): void
    {
        $general = GeneralDescription::factory()->create();
        SpecificDescription::factory()->count(2)->create(['general_description_id' => $general->id]);

        $this->assertCount(2, $general->specificDescriptions);
    }

    /**
     * EN: Deleting a general description is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف الوصف العام يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_a_general_description_soft_deletes_it(): void
    {
        $description = GeneralDescription::factory()->create();

        $description->delete();

        $this->assertSoftDeleted($description);
    }

    /**
     * EN: A general description that has specific descriptions cannot be force deleted.
     * AR: الوصف العام اللي تحته وصوف خاصة ما ينحذف نهائياً.
     */
    public function test_general_description_with_specific_descriptions_cannot_be_force_deleted(): void
    {
        $general = GeneralDescription::factory()->create();
        SpecificDescription::factory()->create(['general_description_id' => $general->id]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23503]');

        $general->forceDelete();
    }
}
