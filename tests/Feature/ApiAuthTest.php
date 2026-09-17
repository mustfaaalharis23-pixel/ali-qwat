<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: An active user who sends their id with the right password gets a token for their device, and their data with it.
     * AR: اليوزر الفعال اللي يدز معرّفه ويا الباسورد الصحيح ياخذ توكن لجهازه، ومعاه بياناته.
     */
    public function test_login_gives_a_token_to_an_active_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/v1/login', [
            'user_id' => $user->id,
            'password' => 'password',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk();
        $this->assertIsString($response->json('token'));
        $this->assertSame($user->id, $response->json('user.id'));
        $this->assertSame(1, DB::table('personal_access_tokens')->where('name', 'iPhone 15')->count());
    }

    /**
     * EN: A wrong password does not give a token, and no token is saved.
     * AR: الباسورد الغلط ما ياخذ توكن، وماكو توكن ينحفظ.
     */
    public function test_a_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->postJson('/api/v1/login', [
            'user_id' => $user->id,
            'password' => 'wrong-password',
            'device_name' => 'iPhone 15',
        ])->assertStatus(422)->assertJsonValidationErrorFor('user_id');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    /**
     * EN: A user who cannot enter the panel does not enter the application either, even with the right password.
     * AR: اليوزر اللي ما يدخل لوحة التحكم ما يدخل التطبيق بعد، حتى لو الباسورد صحيح.
     */
    #[DataProvider('usersThatCannotEnter')]
    public function test_a_user_who_cannot_enter_the_system_cannot_log_in(bool $isActive, bool $isDeleted): void
    {
        $user = User::factory()->create(['is_active' => $isActive]);

        if ($isDeleted) {
            $user->delete();
        }

        $this->postJson('/api/v1/login', [
            'user_id' => $user->id,
            'password' => 'password',
            'device_name' => 'iPhone 15',
        ])->assertStatus(422)->assertJsonValidationErrorFor('user_id');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    /**
     * EN: The users that are not allowed in: the inactive one and the deleted one.
     * AR: اليوزرات الممنوعين: الغير فعال والمحذوف.
     *
     * @return array<string, array{bool, bool}>
     */
    public static function usersThatCannotEnter(): array
    {
        return [
            'inactive' => [false, false],
            'deleted' => [true, true],
        ];
    }

    /**
     * EN: A request without a token does not reach a protected endpoint.
     * AR: الطلب بلا توكن ما يوصل لأي endpoint محمي.
     */
    public function test_a_request_without_a_token_is_unauthorized(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
