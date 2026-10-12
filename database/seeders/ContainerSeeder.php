<?php

namespace Database\Seeders;

use App\Models\Container;
use Illuminate\Database\Seeder;

class ContainerSeeder extends Seeder
{
    /**
     * Fake containers for local development. Running the seeder again does not duplicate them.
     *
     * @var array<int, array{international_number: string, cbm_price: float, loading_date: string, expected_arrival_date: string, notes: ?string}>
     */
    private array $containers = [
        ['international_number' => 'MSCU1234565', 'cbm_price' => 120, 'loading_date' => '2026-08-20', 'expected_arrival_date' => '2026-10-05', 'notes' => 'من نينغبو إلى أم قصر.'],
        ['international_number' => 'CMAU7654321', 'cbm_price' => 135.5, 'loading_date' => '2026-09-10', 'expected_arrival_date' => '2026-10-25', 'notes' => null],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->containers as $container) {
            Container::firstOrCreate(['international_number' => $container['international_number']], $container);
        }
    }
}
