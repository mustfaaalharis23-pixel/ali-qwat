<?php

namespace Database\Seeders;

use App\Enums\OrderPaymentType;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\SpecificDescription;
use App\Models\User;
use Illuminate\Database\Seeder;

class WithdrawalSeeder extends Seeder
{
    /**
     * The placeholder image that the invoice seeder saves.
     */
    private const IMAGE = 'invoices/seeded-invoice.png';

    /**
     * Fake withdrawals for local development, after the orders are seeded: for every cashier, an invoice that withdraws
     * half of the cashier's cash sales. The amount is rounded down to a thousand, and a cashier without cash sales gets no withdrawal.
     * Postpaid money is withdrawn only by customer payments, so CustomerPaymentSeeder seeds it.
     */
    public function run(): void
    {
        $user = User::query()->firstOrFail();
        $everyDate = new Period(null, null);

        foreach (User::query()->where('is_cashier', true)->get() as $cashier) {
            $amount = $this->roundDownToThousand(Order::sales(OrderPaymentType::Cash, $everyDate, $cashier) / 2);

            $this->withdrawal($user, OrderPaymentType::Cash, $amount, $cashier);
        }
    }

    /**
     * Creates an invoice dated today with one line under the first description of the given transfer type, unless the amount is zero.
     */
    private function withdrawal(User $user, OrderPaymentType $transferType, float $amount, ?User $cashier = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $description = SpecificDescription::query()->where('transfer_type', $transferType)->oldest('id')->firstOrFail();
        $generalDescription = $description->generalDescription;

        $invoice = Invoice::create([
            'name' => $description->name,
            'type' => $generalDescription->type,
            'general_description_id' => $generalDescription->id,
            'user_id' => $user->id,
            'cashier_id' => $cashier?->id,
            'date' => today(),
            'images' => [self::IMAGE],
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $description->id,
            'price' => $amount,
        ]);
    }

    private function roundDownToThousand(float $amount): float
    {
        return floor($amount / 1000) * 1000;
    }
}
