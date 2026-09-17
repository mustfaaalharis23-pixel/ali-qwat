<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed simple data for every model on a fresh database.
     * Model events stay on: items create their specific descriptions, and invoice lines and payoffs set their invoice amounts through them.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_super' => true,
            'is_active' => true,
        ]);

        $accountant = User::factory()->create([
            'name' => 'Accountant',
            'email' => 'accountant@example.com',
            'is_active' => true,
        ]);

        User::factory()->cashier()->create([
            'name' => 'Cashier',
            'email' => 'cashier@example.com',
            'is_active' => true,
        ]);

        User::factory()->cashier()->create([
            'name' => 'Cashier 2',
            'email' => 'cashier2@example.com',
            'is_active' => true,
        ]);

        DB::table('user_permissions')->insert([
            ['user_id' => $accountant->id, 'permission' => 'financial_dashboard.view'],
            ['user_id' => $accountant->id, 'permission' => 'invoice.view'],
            ['user_id' => $accountant->id, 'permission' => 'supplier.view'],
            ['user_id' => $accountant->id, 'permission' => 'customer.view'],
            ['user_id' => $accountant->id, 'permission' => 'customer_payment.view'],
        ]);

        $this->call([
            DescriptionSeeder::class,
            ItemSeeder::class,
            SupplierSeeder::class,
            CustomerSeeder::class,
            InvoiceSeeder::class,
            OrderSeeder::class,
            CustomerPaymentSeeder::class,
            WithdrawalSeeder::class,
        ]);
    }
}
