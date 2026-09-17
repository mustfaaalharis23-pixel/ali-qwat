<?php

namespace Database\Seeders;

use App\Enums\CustomerPaymentType;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Fake customers for local development, with and without a phone and email, paying cash or postpaid.
     * Running the seeder again does not duplicate them.
     *
     * @var array<int, array{name: string, phone: ?string, email: ?string, payment_type: CustomerPaymentType}>
     */
    private array $customers = [
        ['name' => 'بصريات النور', 'phone' => '07701234567', 'email' => 'alnoor.optics@example.com', 'payment_type' => CustomerPaymentType::Postpaid],
        ['name' => 'مركز الرؤية الحديثة', 'phone' => '07811122233', 'email' => 'modern.vision@example.com', 'payment_type' => CustomerPaymentType::Postpaid],
        ['name' => 'نظارات الكرادة', 'phone' => '07901234000', 'email' => null, 'payment_type' => CustomerPaymentType::Cash],
        ['name' => 'أحمد علي حسين', 'phone' => '07705556677', 'email' => 'ahmed.ali@example.com', 'payment_type' => CustomerPaymentType::Cash],
        ['name' => 'عيادة الدكتورة سارة للعيون', 'phone' => '07733344455', 'email' => 'dr.sara.eyes@example.com', 'payment_type' => CustomerPaymentType::Postpaid],
        ['name' => 'محلات المنصور للنظارات', 'phone' => null, 'email' => 'mansour.glasses@example.com', 'payment_type' => CustomerPaymentType::Cash],
        ['name' => 'بصريات البصرة', 'phone' => '07801239876', 'email' => null, 'payment_type' => CustomerPaymentType::Postpaid],
        ['name' => 'مصطفى كريم', 'phone' => null, 'email' => null, 'payment_type' => CustomerPaymentType::Cash],
        ['name' => 'مجمع أربيل الطبي', 'phone' => '07501112233', 'email' => 'erbil.medical@example.com', 'payment_type' => CustomerPaymentType::Postpaid],
        ['name' => 'نظارات الشمس - النجف', 'phone' => '07809998877', 'email' => null, 'payment_type' => CustomerPaymentType::Cash],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->customers as $customer) {
            Customer::firstOrCreate(['name' => $customer['name']], $customer);
        }
    }
}
