<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * A larger dataset for the request benchmark (php artisan benchmark:requests): the normal seed, then the items,
 * suppliers, purchase invoices, orders, and payments seeded again and again, so every list has more than one page
 * and a query run once per row shows up in the numbers.
 */
class BenchmarkSeeder extends Seeder
{
    /**
     * How many extra rounds of data are seeded on top of the normal seed.
     */
    private const ROUNDS = 25;

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        foreach (range(1, self::ROUNDS) as $round) {
            $this->callSilent([
                ItemSeeder::class,
                SupplierSeeder::class,
                InvoiceSeeder::class,
                OrderSeeder::class,
                CustomerPaymentSeeder::class,
                WithdrawalSeeder::class,
            ]);
        }
    }
}
