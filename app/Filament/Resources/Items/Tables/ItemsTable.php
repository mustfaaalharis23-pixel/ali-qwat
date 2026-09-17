<?php

namespace App\Filament\Resources\Items\Tables;

use App\Filament\Tables\Columns\IdColumn;
use App\Models\Item;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IdColumn::make('id'),
                ImageColumn::make('image')
                    ->label(__('filament/resources/item.fields.image'))
                    ->disk('public'),
                TextColumn::make('name')
                    ->label(__('filament/resources/item.fields.name'))
                    ->searchable(),
                TextColumn::make('available_quantity')
                    ->label(__('filament/resources/item.fields.available_quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('incoming_quantity')
                    ->label(__('filament/resources/item.fields.incoming_quantity'))
                    ->numeric()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label(__('filament/resources/item.fields.sale_price'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost_price')
                    ->label(__('filament/resources/item.fields.cost_price'))
                    ->numeric()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('viewCostPrice', Item::class) ?? false),
                TextColumn::make('profit_margin')
                    ->label(__('filament/resources/item.fields.profit_margin'))
                    ->state(fn (Item $record): float => $record->profitMargin())
                    ->numeric()
                    ->visible(fn (): bool => auth()->user()?->can('viewCostPrice', Item::class) ?? false),
                IconColumn::make('is_active')
                    ->label(__('filament/resources/item.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('filament/resources/item.filters.status.label'))
                    ->placeholder(__('filament/resources/item.filters.status.all'))
                    ->trueLabel(__('filament/resources/item.filters.status.active'))
                    ->falseLabel(__('filament/resources/item.filters.status.inactive'))
                    ->default(true),
                SelectFilter::make('supplier_id')
                    ->label(__('filament/resources/item.fields.supplier_id'))
                    ->relationship('supplier', 'name_ar')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
