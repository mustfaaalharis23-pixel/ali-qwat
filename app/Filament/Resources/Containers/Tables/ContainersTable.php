<?php

namespace App\Filament\Resources\Containers\Tables;

use App\Filament\Tables\Columns\IdColumn;
use App\Metrics\Currency;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContainersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withSum('invoices', 'cbm')->withSum('invoices', 'transport_cost_usd'))
            ->defaultSort('id', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('international_number')
                    ->label(__('filament/resources/container.fields.international_number'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('cbm_price')
                    ->label(__('filament/resources/container.fields.cbm_price'))
                    ->numeric()
                    ->suffix(' '.Currency::UsDollar->symbol())
                    ->sortable(),
                TextColumn::make('invoices_sum_cbm')
                    ->label(__('filament/resources/container.fields.total_cbm'))
                    ->numeric(maxDecimalPlaces: 3)
                    ->placeholder('0'),
                TextColumn::make('invoices_sum_transport_cost_usd')
                    ->label(__('filament/resources/container.fields.transport_cost'))
                    ->numeric(maxDecimalPlaces: 2)
                    ->placeholder('0')
                    ->suffix(' '.Currency::UsDollar->symbol()),
                TextColumn::make('loading_date')
                    ->label(__('filament/resources/container.fields.loading_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('expected_arrival_date')
                    ->label(__('filament/resources/container.fields.expected_arrival_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label(__('filament/resources/container.fields.notes'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
