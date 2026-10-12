<?php

namespace App\Filament\Resources\Containers\Schemas;

use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Metrics\Currency;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class ContainerForm
{
    /**
     * The expected arrival date is filled this many days after the loading date; the user can still change it.
     */
    private const int DAYS_TO_ARRIVAL = 60;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->label(__('filament/resources/container.fields.id'))
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create'),
                TextInput::make('international_number')
                    ->label(__('filament/resources/container.fields.international_number'))
                    ->helperText(__('filament/resources/container.hints.international_number'))
                    ->placeholder('MSCU1234567')
                    ->mutateStateForValidationUsing(fn (?string $state): ?string => self::normalizeInternationalNumber($state))
                    ->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeInternationalNumber($state))
                    ->regex('/^[A-Z]{4}[0-9]{7}$/')
                    ->unique(ignoreRecord: true),
                MoneyInput::make('cbm_price')
                    ->label(__('filament/resources/container.fields.cbm_price'))
                    ->helperText(__('filament/resources/container.hints.cbm_price'))
                    ->suffix(Currency::UsDollar->symbol())
                    ->minValue(0)
                    ->required(),
                DateInput::make('loading_date')
                    ->label(__('filament/resources/container.fields.loading_date'))
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set): mixed => $set('expected_arrival_date', filled($state) ? Carbon::parse($state)->addDays(self::DAYS_TO_ARRIVAL)->toDateString() : null)),
                DateInput::make('expected_arrival_date')
                    ->label(__('filament/resources/container.fields.expected_arrival_date'))
                    ->helperText(__('filament/resources/container.hints.expected_arrival_date', ['days' => self::DAYS_TO_ARRIVAL]))
                    ->minDate(fn (Get $get): ?string => $get('loading_date'))
                    ->afterOrEqual(fn (Get $get): ?string => $get('loading_date')),
                Textarea::make('notes')
                    ->label(__('filament/resources/container.fields.notes'))
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The international number in capital letters without spaces, as it is written on the container (ISO 6346): MSCU 123456 7 becomes MSCU1234567.
     */
    private static function normalizeInternationalNumber(?string $number): ?string
    {
        return filled($number) ? strtoupper(str_replace(' ', '', $number)) : null;
    }
}
