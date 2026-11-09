<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame($model, __('filament/resources/user.label', locale: $locale));
        $this->assertSame($pluralModel, __('filament/resources/user.plural_label', locale: $locale));

        $items = collect($this->actingAs(User::factory()->create(['is_active' => true, 'is_super' => true]))
            ->withSession(['locale' => $locale])
            ->getJson('/admin-api/navigation')
            ->assertOk()
            ->json('groups'))->flatMap(fn (array $group): array => $group['items']);

        $this->assertSame($navigation, $items->firstWhere('key', 'users')['label']);
    }
}
