<?php

namespace App\Filament\Widgets\CustomerPayments;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerPaymentInfolist
{
    /**
     * The details of a payment in its modal: who paid how much and when, its withdrawal invoice,
     * the postpaid orders it paid with the share of each, and its receipt.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['md' => 2])
            ->components([
                TextEntry::make('customer.name')
                    ->label(__('filament/resources/customer_payment.fields.customer_id'))
                    ->url(fn (CustomerPayment $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
                TextEntry::make('amount')
                    ->label(__('filament/resources/customer_payment.fields.amount'))
                    ->numeric(),
                TextEntry::make('date')
                    ->label(__('filament/resources/customer_payment.fields.date'))
                    ->date('Y/m/d'),
                TextEntry::make('user.name')
                    ->label(__('filament/resources/customer_payment.fields.user_id')),
                TextEntry::make('invoice.name')
                    ->label(__('filament/resources/customer_payment.fields.invoice_id'))
                    ->url(fn (CustomerPayment $record): string => GeneralInvoiceResource::getUrl('view', ['record' => $record->invoice_id])),
                TextEntry::make('notes')
                    ->label(__('filament/resources/customer_payment.fields.notes'))
                    ->placeholder('—'),
                RepeatableEntry::make('allocations')
                    ->label(__('filament/resources/customer_payment.fields.allocations'))
                    ->table([
                        TableColumn::make(__('filament/resources/customer_payment.fields.order')),
                        TableColumn::make(__('filament/resources/customer_payment.fields.order_date')),
                        TableColumn::make(__('filament/resources/customer_payment.fields.order_total')),
                        TableColumn::make(__('filament/resources/customer_payment.fields.allocation_amount')),
                    ])
                    ->schema([
                        TextEntry::make('order_id')
                            ->formatStateUsing(fn (int $state): string => "#{$state}")
                            ->url(fn (CustomerPaymentOrder $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
                        TextEntry::make('order.date')->date('Y/m/d'),
                        TextEntry::make('order.total_amount')->numeric(),
                        TextEntry::make('amount')->numeric(),
                    ])
                    ->columnSpanFull(),
                ImageEntry::make('image')
                    ->label(__('filament/resources/customer_payment.sections.receipt'))
                    ->disk('public')
                    ->visible(fn (CustomerPayment $record): bool => filled($record->image))
                    ->columnSpanFull(),
            ]);
    }
}
