<?php

namespace Database\Seeders;

use App\Models\InvoiceCategory;
use Illuminate\Database\Seeder;

class InvoiceCategorySeeder extends Seeder
{
    /**
     * The first categories of purchase invoices. Running the seeder again does not duplicate them.
     *
     * @var list<string>
     */
    private array $categories = ['إطارات', 'عدسات', 'نظارات طبية', 'عدسات لاصقة'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->categories as $name) {
            InvoiceCategory::firstOrCreate(['name' => $name]);
        }
    }
}
