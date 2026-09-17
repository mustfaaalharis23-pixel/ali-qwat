<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_on_the_waqt_testing_postgres_database(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('waqt_testing', DB::connection()->getDatabaseName());
    }

    public function test_each_test_starts_with_an_empty_database(): void
    {
        $this->assertDatabaseCount('users', 0);

        User::factory()->create();

        $this->assertDatabaseCount('users', 1);
    }
}
