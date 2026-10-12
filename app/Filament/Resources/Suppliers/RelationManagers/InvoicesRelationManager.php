<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Invoice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The purchase invoices of a supplier, shown on its page. Opening a row opens the invoice's page.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/purchase_invoice.plural_label');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modelLabel(__('filament/resources/purchase_invoice.label'))
            ->pluralModelLabel(__('filament/resources/purchase_invoice.plural_label'))
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id')
                    ->resource(PurchaseInvoiceResource::class),
                TextColumn::make('name')
                    ->label(__('filament/resources/invoice.fields.name'))
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
            ])
            ->recordUrl(fn (Invoice $record): string => PurchaseInvoiceResource::getUrl('view', ['record' => $record]));
    }
}
