<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Filament\Resources\Items\ItemResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseInvoiceInfolist
{
    /**
     * The details page of a purchase invoice: its information, amounts, lines, images, and notes.
     * The payoffs are shown under it as a relation manager.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/purchase_invoice.sections.details'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('filament/resources/invoice.fields.name')),
                        TextEntry::make('supplier.name_ar')
                            ->label(__('filament/resources/invoice.fields.supplier_id')),
                        TextEntry::make('date')
                            ->label(__('filament/resources/invoice.fields.date'))
                            ->date('Y/m/d'),
                        TextEntry::make('status')
                            ->label(__('filament/resources/invoice.fields.status'))
                            ->badge(),
                        TextEntry::make('user.name')
                            ->label(__('filament/resources/invoice.fields.user_id')),
                        TextEntry::make('awaited_confirmation')
                            ->label(__('filament/resources/invoice.fields.awaited_confirmation'))
                            ->state(fn (Invoice $record): ?string => $record->awaitedConfirmation()?->getLabel())
                            ->badge()
                            ->color('gray')
                            ->placeholder('—'),
                        TextEntry::make('importerChecker.name')
                            ->label(__('filament/resources/invoice.fields.importer_checker_id'))
                            ->placeholder('—'),
                        TextEntry::make('importer_confirmed_at')
                            ->label(__('filament/resources/invoice.fields.importer_confirmed_at'))
                            ->dateTime('Y/m/d H:i')
                            ->placeholder('—'),
                        TextEntry::make('receiverChecker.name')
                            ->label(__('filament/resources/invoice.fields.receiver_checker_id'))
                            ->placeholder('—'),
                        TextEntry::make('receiver_confirmed_at')
                            ->label(__('filament/resources/invoice.fields.receiver_confirmed_at'))
                            ->dateTime('Y/m/d H:i')
                            ->placeholder('—'),
                    ]),
                Section::make(__('filament/resources/purchase_invoice.sections.amounts'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('amount')
                            ->label(__('filament/resources/invoice.fields.amount'))
                            ->numeric(),
                        TextEntry::make('paid_amount')
                            ->label(__('filament/resources/invoice.fields.paid_amount'))
                            ->numeric()
                            ->color('success'),
                        TextEntry::make('remaining')
                            ->label(__('filament/resources/invoice.fields.remaining'))
                            ->numeric()
                            ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success'),
                    ]),
                RepeatableEntry::make('items')
                    ->label(__('filament/resources/purchase_invoice.fields.items'))
                    ->table([
                        TableColumn::make(__('filament/resources/purchase_invoice.fields.item'))->width('40%'),
                        TableColumn::make(__('filament/resources/invoice_item.fields.boxes_count')),
                        TableColumn::make(__('filament/resources/invoice_item.fields.box_quantity')),
                        TableColumn::make(__('filament/resources/invoice_item.fields.quantity')),
                        TableColumn::make(__('filament/resources/invoice_item.fields.price')),
                    ])
                    ->schema([
                        TextEntry::make('specificDescription.name')
                            ->color(fn (InvoiceItem $record): ?string => self::itemUrl($record) === null ? null : 'primary')
                            ->url(fn (InvoiceItem $record): ?string => self::itemUrl($record), shouldOpenInNewTab: true),
                        TextEntry::make('boxes_count')->numeric(),
                        TextEntry::make('box_quantity')->numeric(),
                        TextEntry::make('quantity')->numeric(),
                        TextEntry::make('price')->numeric(),
                    ])
                    ->columnSpanFull(),
                ImageEntry::make('images')
                    ->label(__('filament/resources/invoice.fields.images'))
                    ->disk('public')
                    ->columnSpanFull(),
                TextEntry::make('notes')
                    ->label(__('filament/resources/invoice.fields.notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The page of the line's item, so its name opens it in a new tab. It is not a link when the line has no item,
     * or when the user cannot open items.
     */
    private static function itemUrl(InvoiceItem $line): ?string
    {
        $item = $line->item;

        if ($item === null || ! ItemResource::canView($item)) {
            return null;
        }

        return ItemResource::getUrl('view', ['record' => $item]);
    }
}
