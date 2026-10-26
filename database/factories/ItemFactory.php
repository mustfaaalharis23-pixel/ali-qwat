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
     * Indicate that the item has stock: a purchase invoice both sides confirmed, of these dozens at this cost of one dozen,
     * bought in the item's packets, or as one packet of all their pieces for an item without a packet size.
     * It is the only way stock enters, so every test that sells an item uses it, and the item's cost of a dozen comes from it.
     */
    public function stocked(float $dozens = 1000, float $dozenCost = 100): static
    {
        return $this->afterCreating(function (Item $item) use ($dozens, $dozenCost): void {
            $pieces = $dozens * Item::PIECES_IN_A_DOZEN;
            $piecesPerPacket = $item->pieces_per_packet ?? $pieces;

            InvoiceItem::factory()->create([
                'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
                'specific_description_id' => $item->specificDescription->id,
                'boxes_count' => $pieces / $piecesPerPacket,
                'pieces_per_packet' => $piecesPerPacket,
                'price' => $dozens * $dozenCost,
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
