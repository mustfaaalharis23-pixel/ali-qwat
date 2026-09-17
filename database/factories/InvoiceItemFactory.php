<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\SpecificDescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state: a line whose description fits its invoice.
     * A purchase invoice gets the description of a new item; a general invoice gets a description under its general description.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'specific_description_id' => function (array $attributes): int {
                $invoice = Invoice::query()->findOrFail($attributes['invoice_id']);

                if ($invoice->isPurchase()) {
                    return Item::factory()->create()->specificDescription->id;
                }

                return SpecificDescription::factory()->create([
                    'general_description_id' => $invoice->general_description_id,
                ])->id;
            },
            'price' => fake()->randomFloat(2, 1, 1000),
            'quantity' => 1,
        ];
    }
}
