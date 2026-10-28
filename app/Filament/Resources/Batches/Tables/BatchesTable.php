<?php

namespace App\Filament\Resources\Batches\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Batch;
use App\Models\BatchColor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class BatchesTable
{
    /**
     * The batches table. It has no actions: a batch is written with its purchase line, never by hand.
     * Inside an item's own batches tab the item column is hidden, because it is always the same item.
     * The lens power column and filter show what came in of each power, and the colors column the pieces of each color in the batch.
     */
    public static function configure(Table $table, bool $showItem = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['item', 'invoice', 'lensPower', 'colors.color']))
            ->defaultSort('id', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('item.name')
                    ->label(__('filament/resources/batch.fields.item_id'))
                    ->searchable()
                    ->visible($showItem),
                TextColumn::make('lensPower.value')
                    ->label(__('filament/resources/batch.fields.lens_power_id'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('colors')
                    ->label(__('filament/resources/batch.fields.colors'))
                    ->state(fn (Batch $record): ?string => $record->colors->isEmpty() ? null : $record->colors
                        ->map(fn (BatchColor $color): string => __('filament/resources/invoice_item.group.color', [
                            'color' => $color->color?->name,
                            'pieces' => Number::format($color->pieces, maxPrecision: 2),
                        ]))
                        ->implode(__('filament/resources/invoice_item.group.separator')))
                    ->placeholder('—')
                    ->wrap(),
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
                SelectFilter::make('lens_power_id')
                    ->label(__('filament/resources/batch.fields.lens_power_id'))
                    ->relationship('lensPower', 'value', fn (Builder $query): Builder => $query->orderBy('value')),
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
