<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'image' => 'items/'.fake()->uuid().'.jpg',
        ];
    }

    /**
     * Indicate that the item has stock: a purchase invoice both sides confirmed, of this quantity at this unit cost.
     * It is the only way stock enters, so every test that sells an item uses it, and the item's cost comes from it.
     */
    public function stocked(float $quantity = 1000, float $unitCost = 100): static
    {
        return $this->afterCreating(function (Item $item) use ($quantity, $unitCost): void {
            InvoiceItem::factory()->create([
                'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
                'specific_description_id' => $item->specificDescription->id,
                'boxes_count' => 1,
                'box_quantity' => $quantity,
                'price' => $quantity * $unitCost,
            ]);
        });
    }

    /**
     * Indicate that the item is not active.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
