<?php

namespace App\Filament\Resources\PurchaseInvoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\Actions\ConfirmInvoiceAction;
use App\Filament\Resources\PurchaseInvoices\Actions\RevertConfirmationAction;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Invoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PurchaseInvoicesTable
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
                TextColumn::make('supplier.name_ar')
                    ->label(__('filament/resources/invoice.fields.supplier_id'))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('filament/resources/invoice.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('filament/resources/invoice.fields.amount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label(__('filament/resources/invoice.fields.paid_amount'))
                    ->numeric(),
                TextColumn::make('remaining')
                    ->label(__('filament/resources/invoice.fields.remaining'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('filament/resources/invoice.fields.status'))
                    ->badge(),
                TextColumn::make('awaited_confirmation')
                    ->label(__('filament/resources/invoice.fields.awaited_confirmation'))
                    ->state(fn (Invoice $record): ?string => $record->awaitedConfirmation()?->getLabel())
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label(__('filament/resources/invoice.fields.user_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label(__('filament/resources/invoice.fields.supplier_id'))
                    ->relationship('supplier', 'name_ar')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label(__('filament/resources/invoice.fields.status'))
                    ->options(InvoiceStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ConfirmInvoiceAction::make(),
                RevertConfirmationAction::make(),
                ViewAction::make(),
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
