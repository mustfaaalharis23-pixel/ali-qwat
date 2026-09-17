<?php

namespace Tests\Unit;

use App\Filament\Resources\Items\ItemResource;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ItemResourceTest extends TestCase
{
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
        App::setLocale($locale);

        $this->assertSame($model, ItemResource::getModelLabel());
        $this->assertSame($pluralModel, ItemResource::getPluralModelLabel());
        $this->assertSame($navigation, ItemResource::getNavigationLabel());
    }
}
