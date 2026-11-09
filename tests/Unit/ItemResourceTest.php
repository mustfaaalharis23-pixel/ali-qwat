<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ItemResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The items screen labels in each language: one item, many items, and the sidebar link.
     * AR: عناوين شاشة الأصناف بكل لغة: صنف واحد، وأكثر من صنف، ورابط القائمة الجانبية.
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function labels(): array
    {
        return [
            'Arabic' => ['ar', 'منتج', 'المنتجات', 'المنتجات'],
            'English' => ['en', 'Item', 'Items', 'Items'],
        ];
    }

    /**
     * EN: The items screen shows its labels in the current language.
     * AR: شاشة الأصناف تعرض عناوينها باللغة الحالية.
     */
    #[DataProvider('labels')]
    public function test_labels_are_shown_in_the_current_language(string $locale, string $model, string $pluralModel, string $navigation): void
    {
        $this->assertSame($model, __('filament/resources/item.label', locale: $locale));
        $this->assertSame($pluralModel, __('filament/resources/item.plural_label', locale: $locale));

        $items = collect($this->actingAs(User::factory()->create(['is_active' => true, 'is_super' => true]))
            ->withSession(['locale' => $locale])
            ->getJson('/admin-api/navigation')
            ->assertOk()
            ->json('groups'))->flatMap(fn (array $group): array => $group['items']);

        $this->assertSame($navigation, $items->firstWhere('key', 'items')['label']);
    }
}
