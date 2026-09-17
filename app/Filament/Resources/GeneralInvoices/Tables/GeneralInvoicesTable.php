<?php

namespace App\Filament\Resources\GeneralInvoices\Tables;

use App\Enums\InvoiceType;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Invoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GeneralInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/invoice.fields.name'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('filament/resources/invoice.fields.type'))
                    ->badge(),
                TextColumn::make('generalDescription.name')
                    ->label(__('filament/resources/invoice.fields.general_description_id')),
                TextColumn::make('date')
                    ->label(__('filament/resources/invoice.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('filament/resources/invoice.fields.amount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cashier.name')
                    ->label(__('filament/resources/invoice.fields.cashier_id')),
                TextColumn::make('user.name')
                    ->label(__('filament/resources/invoice.fields.user_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('filament/resources/invoice.fields.type'))
                    ->options(InvoiceType::class),
                SelectFilter::make('general_description_id')
                    ->label(__('filament/resources/invoice.fields.general_description_id'))
                    ->relationship(
                        'generalDescription',
                        'name',
                        fn (Builder $query): Builder => $query->where('is_purchases_description', false),
                    )
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (Invoice $record): bool => $record->isMoneyTransfer()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make()
                        ->superOnly(),
                ]),
            ]);
    }
}
