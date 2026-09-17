<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Item;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The items (products) of a supplier, shown on its page. Opening a row opens the item's page.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/item.plural_label');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel(__('filament/resources/item.label'))
            ->pluralModelLabel(__('filament/resources/item.plural_label'))
            ->columns([
                IdColumn::make('id'),
                ImageColumn::make('image')
                    ->label(__('filament/resources/item.fields.image'))
                    ->disk('public'),
                TextColumn::make('name')
                    ->label(__('filament/resources/item.fields.name'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('filament/resources/item.fields.is_active'))
                    ->boolean(),
            ])
            ->recordUrl(fn (Item $record): string => ItemResource::getUrl('view', ['record' => $record]));
    }
}
