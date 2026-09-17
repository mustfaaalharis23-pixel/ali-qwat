<?php

namespace Tests\Unit;

use App\Models\GeneralDescription;
use App\Models\Item;
use App\Models\SpecificDescription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpecificDescriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The required columns: the general description and the name.
     * AR: الأعمدة الإجبارية: الوصف العام والاسم.
     *
     * @return array<string, array{string}>
     */
    public static function requiredColumns(): array
    {
        return [
            'general_description_id' => ['general_description_id'],
            'name' => ['name'],
        ];
    }

    /**
     * EN: A specific description cannot be saved in the database without a general description or without a name.
     * AR: الوصف الخاص ما ينحفظ بالداتابيس بدون وصف عام أو بدون اسم.
     */
    #[DataProvider('requiredColumns')]
    public function test_specific_description_cannot_be_saved_without_a_required_column(string $column): void
    {
        $row = ['general_description_id' => GeneralDescription::factory()->create()->id, 'name' => 'Cashier'];
        unset($row[$column]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23502]');

        DB::table('specific_descriptions')->insert($row);
    }

    /**
     * EN: A specific description can be saved without an item, and its item is null.
     * AR: الوصف الخاص ينحفظ بدون مادة، ومادته تكون null.
     */
    public function test_specific_description_can_be_saved_without_an_item(): void
    {
        DB::table('specific_descriptions')->insert([
            'general_description_id' => GeneralDescription::factory()->create()->id,
            'name' => 'Cashier',
        ]);

        $this->assertDatabaseHas('specific_descriptions', [
            'name' => 'Cashier',
            'item_id' => null,
        ]);
    }

    /**
     * EN: A specific description reaches the general description it is under.
     * AR: الوصف الخاص يوصل للوصف العام اللي هو تحته.
     */
    public function test_specific_description_belongs_to_its_general_description(): void
    {
        $general = GeneralDescription::factory()->create();

        $specific = SpecificDescription::factory()->create(['general_description_id' => $general->id]);

        $this->assertTrue($specific->generalDescription->is($general));
    }

    /**
     * EN: Two specific descriptions cannot belong to the same item.
     * AR: وصفين خاصين ما يكدرون ينتمون لنفس المادة.
     */
    public function test_two_specific_descriptions_cannot_belong_to_the_same_item(): void
    {
        $item = Item::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23505]');

        DB::table('specific_descriptions')->insert([
            'general_description_id' => GeneralDescription::purchases()->id,
            'item_id' => $item->id,
            'name' => 'Duplicate',
        ]);
    }

    /**
     * EN: Deleting a specific description is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف الوصف الخاص يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_a_specific_description_soft_deletes_it(): void
    {
        $description = SpecificDescription::factory()->create();

        $description->delete();

        $this->assertSoftDeleted($description);
    }
}
