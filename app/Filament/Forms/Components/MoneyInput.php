<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * The amount of money field used across the panel: while typing, the number gets thousands separators (1,500,000.50).
 * The separators are removed before validation (stripCharacters) and from the value the form hands over for saving
 * (Filament only strips them for validation), so the saved value is always a plain number.
 * It stays a text field, because a number field cannot show the separators.
 */
class MoneyInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->numeric()
            ->type('text')
            ->mask(RawJs::make('$money($input)'))
            ->stripCharacters(',')
            ->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state) ? str_replace(',', '', $state) : $state);
    }
}
