<?php

namespace Database\Seeders;

use App\Models\LensPower;
use Illuminate\Database\Seeder;

class LensPowerSeeder extends Seeder
{
    /**
     * The lens powers of reading glasses, from 0.50 to 4.50 in steps of a quarter: 17 powers.
     * Running the seeder again does not duplicate them.
     */
    public function run(): void
    {
        for ($quarters = 2; $quarters <= 18; $quarters++) {
            LensPower::firstOrCreate(['value' => $quarters / 4]);
        }
    }
}
