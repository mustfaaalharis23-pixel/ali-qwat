<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemGroup;
use App\Models\LensPower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItemGroup>
 */
class InvoiceItemGroupFactory extends Factory
{
    /**
     * Define the model's default state: one packet of a purchase line at a power.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_item_id' => InvoiceItem::factory()->state(['invoice_id' => Invoice::factory()->purchase()]),
            'lens_power_id' => LensPower::factory(),
            'boxes_count' => 1,
        ];
    }
}
