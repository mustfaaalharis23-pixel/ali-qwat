<?php

namespace Tests\Unit;

use App\Enums\SupplierPaymentType;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierPaymentTypeTest extends TestCase
{
    /**
     * EN: The payment type has exactly two values: cash and postpaid.
     * AR: نوع الدفع إله قيمتين بس: cash و postpaid.
     */
    public function test_payment_type_has_cash_and_postpaid_only(): void
    {
        $this->assertSame(
            ['cash', 'postpaid'],
            array_column(SupplierPaymentType::cases(), 'value'),
        );
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

        $this->assertSame($cash, SupplierPaymentType::Cash->getLabel());
        $this->assertSame($postpaid, SupplierPaymentType::Postpaid->getLabel());
    }
}
