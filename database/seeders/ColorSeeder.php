<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    /**
     * The colors the frames come in. Running the seeder again does not duplicate them.
     */
    public function run(): void
    {
        foreach (['أحمر', 'أزرق', 'أسود', 'بني', 'رصاصي', 'شفاف', 'ذهبي', 'فضي'] as $name) {
            Color::firstOrCreate(['name' => $name]);
        }
    }
}
