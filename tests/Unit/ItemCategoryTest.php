<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ItemCategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A category that still has items, such as Frames with one item, cannot be deleted; it stays as it is.
     * AR: الفئة اللي بيها مواد، مثل "إطارات" وبيها مادة وحدة، ما تنحذف، وتبقى مثل ما هي.
     */
    public function test_a_category_with_items_cannot_be_deleted(): void
    {
        $category = ItemCategory::factory()->create(['name' => 'إطارات']);
        Item::factory()->create(['item_category_id' => $category->id]);

        try {
            $category->delete();
            $this->fail('A category with items must not be deleted.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSoftDeleted($category);
    }

    /**
     * EN: A category with no items is deleted normally.
     * AR: الفئة اللي ما بيها مواد تنحذف عادي.
     */
    public function test_a_category_without_items_can_be_deleted(): void
    {
        $category = ItemCategory::factory()->create();

        $category->delete();

        $this->assertSoftDeleted($category);
    }
}
