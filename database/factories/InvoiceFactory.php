<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state: a general invoice under a new general description of the same type, with no supplier.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(InvoiceType::cases()),
            'general_description_id' => fn (array $attributes): int => GeneralDescription::factory()->create(['type' => $attributes['type']])->id,
            'supplier_id' => null,
            'user_id' => User::factory(),
            'date' => today(),
            'images' => ['invoices/'.fake()->uuid().'.jpg'],
        ];
    }

    /**
     * Indicate that the importer confirmed the invoice, so it waits for the receiver.
     */
    public function importerConfirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::ImporterConfirmed,
            'importer_checker_id' => User::factory(),
            'importer_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that both the importer and the receiver confirmed the invoice.
     */
    public function confirmed(): static
    {
        return $this->importerConfirmed()->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Confirmed,
            'receiver_checker_id' => User::factory(),
            'receiver_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the invoice is a purchase invoice, under the purchases description, with a new supplier and a price of one CBM.
     */
    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'general_description_id' => GeneralDescription::purchases()->id,
            'type' => InvoiceType::Output,
            'supplier_id' => Supplier::factory(),
            'cbm_price' => fake()->randomFloat(2, 80, 200),
        ]);
    }
}
