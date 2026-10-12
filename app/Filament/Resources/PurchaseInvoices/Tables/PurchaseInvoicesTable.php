<?php

namespace App\Filament\Resources\PurchaseInvoices\Tables;

use App\Enums\InvoiceStage;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Containers\ContainerResource;
use App\Filament\Resources\PurchaseInvoices\Actions\AdvanceStageAction;
use App\Filament\Resources\PurchaseInvoices\Actions\ConfirmInvoiceAction;
use App\Filament\Resources\PurchaseInvoices\Actions\PrintLabelAction;
use App\Filament\Resources\PurchaseInvoices\Actions\PrintPurchaseInvoiceAction;
use App\Filament\Resources\PurchaseInvoices\Actions\RevertConfirmationAction;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
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
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['container', 'category', 'splitParts:id,split_from_invoice_id']))
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/invoice.fields.name'))
                    ->description(fn (Invoice $record): ?View => PurchaseInvoiceResource::splitFacts($record))
                    ->searchable(),
                TextColumn::make('supplier.name_ar')
                    ->label(__('filament/resources/invoice.fields.supplier_id'))
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label(__('filament/resources/invoice.fields.invoice_category_id'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('container_id')
                    ->label(__('filament/resources/invoice.fields.container_id'))
                    ->state(fn (Invoice $record): ?string => $record->container?->displayName())
                    ->color(fn (Invoice $record): ?string => ContainerResource::viewUrlFor($record->container) === null ? null : 'primary')
                    ->url(fn (Invoice $record): ?string => ContainerResource::viewUrlFor($record->container))
                    ->placeholder('—'),
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
                TextColumn::make('stage')
                    ->label(__('filament/resources/invoice.fields.stage'))
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
                SelectFilter::make('stage')
                    ->label(__('filament/resources/invoice.fields.stage'))
                    ->options(InvoiceStage::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                AdvanceStageAction::make(),
                ConfirmInvoiceAction::make(),
                RevertConfirmationAction::make(),
                PrintLabelAction::make(),
                ViewAction::make(),
                PrintPurchaseInvoiceAction::make(),
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
