<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payoff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payoff>
 */
class PayoffFactory extends Factory
{
    /**
     * Define the model's default state: a cash payoff of 100 on a new purchase invoice of 1000.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => fn (): int => InvoiceItem::factory()->create([
                'invoice_id' => Invoice::factory()->purchase(),
                'price' => 1000,
            ])->invoice_id,
            'amount' => 100,
            'date' => today(),
        ];
    }
}
