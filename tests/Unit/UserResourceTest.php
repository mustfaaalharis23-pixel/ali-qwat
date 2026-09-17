<?php

namespace Tests\Unit;

use App\Filament\Resources\Users\UserResource;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    /**
     * EN: The users screen labels in each language: one user, many users, and the sidebar link.
     * AR: عناوين شاشة اليوزرز بكل لغة: يوزر واحد، وأكثر من يوزر، ورابط القائمة الجانبية.
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function labels(): array
    {
        return [
            'Arabic' => ['ar', 'مستخدم', 'المستخدمون', 'المستخدمون'],
            'English' => ['en', 'User', 'Users', 'Users'],
        ];
    }

    /**
     * EN: The users screen shows its labels in the current language.
     * AR: شاشة اليوزرز تعرض عناوينها باللغة الحالية.
     */
    #[DataProvider('labels')]
    public function test_labels_are_shown_in_the_current_language(string $locale, string $model, string $pluralModel, string $navigation): void
    {
        App::setLocale($locale);

        $this->assertSame($model, UserResource::getModelLabel());
        $this->assertSame($pluralModel, UserResource::getPluralModelLabel());
        $this->assertSame($navigation, UserResource::getNavigationLabel());
    }
}
