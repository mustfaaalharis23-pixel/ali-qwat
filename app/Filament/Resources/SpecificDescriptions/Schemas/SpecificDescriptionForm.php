<?php

namespace App\Filament\Resources\SpecificDescriptions\Schemas;

use App\Enums\InvoiceType;
use App\Enums\OrderPaymentType;
use App\Models\SpecificDescription;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SpecificDescriptionForm
{
    /**
     * New descriptions go under any general description except the purchases one, whose descriptions are created with the items.
     * The type (input or output) is chosen first, and only the general descriptions of that type are shown. It is not saved.
     * An item description is shown read only.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('type')
                    ->label(__('filament/resources/specific_description.fields.type'))
                    ->options(InvoiceType::class)
                    ->default(InvoiceType::Output)
                    ->inline()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (ToggleButtons $component, ?SpecificDescription $record): void {
                        if ($record !== null) {
                            $component->state($record->generalDescription()->withTrashed()->first()?->type);
                        }
                    })
                    ->afterStateUpdated(fn (Set $set) => $set('general_description_id', null))
                    ->required()
                    ->disabled(fn (?SpecificDescription $record): bool => $record?->item_id !== null),
                Select::make('general_description_id')
                    ->label(__('filament/resources/specific_description.fields.general_description'))
                    ->relationship(
                        'generalDescription',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->where('is_purchases_description', false)
                            ->where('type', $get('type')),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (?SpecificDescription $record, Get $get): bool => $record?->item_id !== null || blank($get('type'))),
                TextInput::make('name')
                    ->label(__('filament/resources/specific_description.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->disabled(fn (?SpecificDescription $record): bool => $record?->item_id !== null),
                Select::make('transfer_type')
                    ->label(__('filament/resources/specific_description.fields.transfer_type'))
                    ->options(OrderPaymentType::class)
                    ->placeholder('—')
                    ->disabled(fn (?SpecificDescription $record): bool => $record?->item_id !== null)
                    ->superOnly(),
            ]);
    }
}
