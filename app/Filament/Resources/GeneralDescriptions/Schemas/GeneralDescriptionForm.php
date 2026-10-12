<?php

namespace App\Filament\Resources\GeneralDescriptions\Schemas;

use App\Enums\InvoiceType;
use App\Models\GeneralDescription;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class GeneralDescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/general_description.sections.details'))
                    ->icon(Heroicon::OutlinedTag)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/resources/general_description.fields.name'))
                            ->required()
                            ->maxLength(255),
                        ToggleButtons::make('type')
                            ->label(__('filament/resources/general_description.fields.type'))
                            ->options(InvoiceType::class)
                            ->inline()
                            ->required(),
                        Toggle::make('is_capital_movement')
                            ->label(__('filament/resources/general_description.fields.is_capital_movement'))
                            ->helperText(__('filament/resources/general_description.hints.is_capital_movement'))
                            ->visible(fn (?GeneralDescription $record): bool => $record?->is_purchases_description !== true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
