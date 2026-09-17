<?php

namespace Tests\Unit;

use App\Enums\CustomerPaymentType;
use App\Models\Customer;
use App\Policies\CustomerPolicy;
use App\Policies\ModelPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A customer can be saved with only its name and payment type; the phone, email, and contract stay null.
     * AR: الزبون ينحفظ بس بالاسم ونوع الدفع، والهاتف والإيميل والعقد يبقون null.
     */
    public function test_customer_can_be_saved_with_only_the_name_and_payment_type(): void
    {
        DB::table('customers')->insert(['name' => 'Ahmed Ali', 'payment_type' => 'cash']);

        $this->assertDatabaseHas('customers', [
            'name' => 'Ahmed Ali',
            'phone' => null,
            'email' => null,
            'contract' => null,
        ]);
    }

    /**
     * EN: The required columns: name and payment type. The payment type has no default value.
     * AR: الأعمدة الإجبارية: الاسم ونوع الدفع. نوع الدفع ما إله قيمة افتراضية.
     *
     * @return array<string, array{string}>
     */
    public static function requiredColumns(): array
    {
        return [
            'name' => ['name'],
            'payment_type' => ['payment_type'],
        ];
    }

    /**
     * EN: A customer cannot be saved in the database without any of its required columns.
     * AR: الزبون ما ينحفظ بالداتابيس إذا ناقصه أي عمود إجباري.
     */
    #[DataProvider('requiredColumns')]
    public function test_customer_cannot_be_saved_without_a_required_column(string $column): void
    {
        $row = ['name' => 'Ahmed Ali', 'payment_type' => 'cash'];
        unset($row[$column]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23502]');

        DB::table('customers')->insert($row);
    }

    /**
     * EN: All the customer's data is saved as entered, the phone keeps its leading zero, and the payment type is read back as the enum.
     * AR: كل بيانات الزبون تنحفظ مثل ما انكتبت، والهاتف يبقى بالصفر اللي بأوله، ونوع الدفع يرجع كـ enum.
     */
    public function test_customer_data_is_saved_as_entered(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Ahmed Ali',
            'phone' => '07701234567',
            'email' => 'ahmed@example.com',
            'contract' => 'customers/contracts/ahmed.jpg',
            'payment_type' => CustomerPaymentType::Postpaid,
        ])->fresh();

        $this->assertSame('Ahmed Ali', $customer->name);
        $this->assertSame('07701234567', $customer->phone);
        $this->assertSame('ahmed@example.com', $customer->email);
        $this->assertSame('customers/contracts/ahmed.jpg', $customer->contract);
        $this->assertSame(CustomerPaymentType::Postpaid, $customer->payment_type);
    }

    /**
     * EN: The payment type has exactly two values: cash and postpaid.
     * AR: نوع الدفع إله قيمتين بس: كاش وآجل.
     */
    public function test_payment_type_has_cash_and_postpaid_only(): void
    {
        $this->assertSame(['cash', 'postpaid'], array_column(CustomerPaymentType::cases(), 'value'));
    }

    /**
     * EN: The payment type labels in each language.
     * AR: تسميات نوع الدفع بكل لغة.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function labels(): array
    {
        return [
            'Arabic' => ['ar', 'كاش', 'آجل'],
            'English' => ['en', 'Cash', 'Postpaid'],
        ];
    }

    /**
     * EN: Each payment type shows its label in the current language.
     * AR: كل نوع دفع يعرض تسميته باللغة الحالية.
     */
    #[DataProvider('labels')]
    public function test_payment_type_label_is_shown_in_the_current_language(string $locale, string $cash, string $postpaid): void
    {
        App::setLocale($locale);

        $this->assertSame($cash, CustomerPaymentType::Cash->getLabel());
        $this->assertSame($postpaid, CustomerPaymentType::Postpaid->getLabel());
    }

    /**
     * EN: Laravel finds CustomerPolicy for the Customer model, and it inherits ModelPolicy, so customers follow the same permission rules.
     * AR: Laravel يلكي CustomerPolicy لموديل Customer، وهو يورث ModelPolicy، فالزبائن يتبعون نفس قواعد الصلاحيات.
     */
    public function test_customer_uses_customer_policy_that_inherits_model_policy(): void
    {
        $policy = Gate::getPolicyFor(Customer::class);

        $this->assertInstanceOf(CustomerPolicy::class, $policy);
        $this->assertInstanceOf(ModelPolicy::class, $policy);
    }

    /**
     * EN: Deleting a customer is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف الزبون يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_a_customer_soft_deletes_it(): void
    {
        $customer = Customer::factory()->create();

        $customer->delete();

        $this->assertSoftDeleted($customer);
    }
}
