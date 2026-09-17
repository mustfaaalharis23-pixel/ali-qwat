<?php

namespace App\Filament\Resources\GeneralDescriptions\Tables;

use App\Enums\InvoiceType;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\GeneralDescription;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class GeneralDescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/general_description.fields.name'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('filament/resources/general_description.fields.type'))
                    ->badge(),
                IconColumn::make('is_purchases_description')
                    ->label(__('filament/resources/general_description.fields.is_purchases_description'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('filament/resources/general_description.fields.type'))
                    ->options(InvoiceType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->checkIfRecordIsSelectableUsing(fn (GeneralDescription $record): bool => ! $record->is_purchases_description)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
