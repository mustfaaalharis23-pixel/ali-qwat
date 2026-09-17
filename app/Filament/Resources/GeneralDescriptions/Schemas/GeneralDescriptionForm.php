<?php

namespace App\Filament\Resources\GeneralDescriptions\Schemas;

use App\Enums\InvoiceType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;

class GeneralDescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/resources/general_description.fields.name'))
                    ->required()
                    ->maxLength(255),
                ToggleButtons::make('type')
                    ->label(__('filament/resources/general_description.fields.type'))
                    ->options(InvoiceType::class)
                    ->inline()
                    ->required(),
            ]);
    }
}
