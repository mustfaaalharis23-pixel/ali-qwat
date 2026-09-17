<?php

namespace App\Filament\Resources\Batches\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Batch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BatchesTable
{
    /**
     * The batches table. It has no actions: a batch is written with its purchase line, never by hand.
     * Inside an item's own batches tab the item column is hidden, because it is always the same item.
     */
    public static function configure(Table $table, bool $showItem = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['item', 'invoice']))
            ->defaultSort('id', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('item.name')
                    ->label(__('filament/resources/batch.fields.item_id'))
                    ->searchable()
                    ->visible($showItem),
                TextColumn::make('invoice_id')
                    ->label(__('filament/resources/batch.fields.invoice_id'))
                    ->searchable()
                    ->sortable()
                    ->color(fn (Batch $record): ?string => self::invoiceUrl($record) === null ? null : 'primary')
                    ->url(fn (Batch $record): ?string => self::invoiceUrl($record)),
                TextColumn::make('invoice.date')
                    ->label(__('filament/resources/batch.fields.date'))
                    ->date(),
                TextColumn::make('quantity')
                    ->label(__('filament/resources/batch.fields.quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('purchase_price')
                    ->label(__('filament/resources/batch.fields.purchase_price'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('invoice.status')
                    ->label(__('filament/resources/batch.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament/resources/batch.fields.status'))
                    ->options(InvoiceStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'], fn (Builder $query, string $status): Builder => $query
                            ->whereHas('invoice', fn (Builder $query): Builder => $query->where('status', $status)))),
                TrashedFilter::make(),
            ]);
    }

    /**
     * The page of the batch's invoice, so its number opens it. It is not a link when the user cannot open the invoice.
     */
    private static function invoiceUrl(Batch $batch): ?string
    {
        $invoice = $batch->invoice;

        if ($invoice === null || ! PurchaseInvoiceResource::canView($invoice)) {
            return null;
        }

        return PurchaseInvoiceResource::getUrl('view', ['record' => $invoice]);
    }
}
