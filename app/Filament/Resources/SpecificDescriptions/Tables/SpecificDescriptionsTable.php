<?php

namespace App\Filament\Resources\SpecificDescriptions\Tables;

use App\Filament\Tables\Columns\IdColumn;
use App\Models\SpecificDescription;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SpecificDescriptionsTable
{
    /**
     * Item descriptions are hidden by default and cannot be edited or deleted here, because they follow their items.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/specific_description.fields.name'))
                    ->searchable(),
                TextColumn::make('generalDescription.name')
                    ->label(__('filament/resources/specific_description.fields.general_description')),
                TextColumn::make('item.name')
                    ->label(__('filament/resources/specific_description.fields.item'))
                    ->placeholder('—'),
                TextColumn::make('transfer_type')
                    ->label(__('filament/resources/specific_description.fields.transfer_type'))
                    ->badge()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('general_description_id')
                    ->label(__('filament/resources/specific_description.fields.general_description'))
                    ->relationship('generalDescription', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('item_id')
                    ->label(__('filament/resources/specific_description.filters.item_descriptions'))
                    ->nullable()
                    ->default(false),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (SpecificDescription $record): bool => $record->item_id !== null),
            ])
            ->checkIfRecordIsSelectableUsing(fn (SpecificDescription $record): bool => $record->item_id === null)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
