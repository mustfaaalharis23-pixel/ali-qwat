<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Item;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    /**
     * The details page of an order: its information, amounts, lines, and notes.
     * What the order cost us and what it earned, on the order and on each line, show only to a user
     * who has the item.view_cost_price permission.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/order.sections.details'))
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label(__('filament/resources/order.fields.customer_id')),
                        TextEntry::make('date')
                            ->label(__('filament/resources/order.fields.date'))
                            ->date('Y/m/d'),
                        TextEntry::make('payment_type')
                            ->label(__('filament/resources/order.fields.payment_type'))
                            ->badge(),
                        TextEntry::make('cashier.name')
                            ->label(__('filament/resources/order.fields.cashier_id')),
                        TextEntry::make('user.name')
                            ->label(__('filament/resources/order.fields.user_id')),
                    ]),
                Section::make(__('filament/resources/order.sections.amounts'))
                    ->columns(['md' => 2, 'xl' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('amount')
                            ->label(__('filament/resources/order.fields.amount'))
                            ->numeric(),
                        TextEntry::make('total_amount')
                            ->label(__('filament/resources/order.fields.total_amount'))
                            ->numeric(),
                        TextEntry::make('paid_amount')
                            ->label(__('filament/resources/order.fields.paid_amount'))
                            ->numeric(),
                        TextEntry::make('remaining')
                            ->label(__('filament/resources/order.fields.remaining'))
                            ->numeric(),
                        TextEntry::make('total_cost')
                            ->label(__('filament/resources/order.fields.total_cost'))
                            ->numeric()
                            ->visible(fn (): bool => self::canSeeTheCost()),
                        TextEntry::make('profit')
                            ->label(__('filament/resources/order.fields.profit'))
                            ->numeric()
                            ->color('success')
                            ->visible(fn (): bool => self::canSeeTheCost()),
                    ]),
                RepeatableEntry::make('items')
                    ->label(__('filament/resources/order.fields.items'))
                    ->table(array_values(array_filter([
                        TableColumn::make(__('filament/resources/order.fields.item'))->width('40%'),
                        TableColumn::make(__('filament/resources/order.fields.quantity')),
                        TableColumn::make(__('filament/resources/order.fields.price')),
                        TableColumn::make(__('filament/resources/order.fields.total_price')),
                        self::canSeeTheCost() ? TableColumn::make(__('filament/resources/order.fields.cost_price')) : null,
                        self::canSeeTheCost() ? TableColumn::make(__('filament/resources/order.fields.profit')) : null,
                    ])))
                    ->schema(array_values(array_filter([
                        TextEntry::make('item.name'),
                        TextEntry::make('quantity')->numeric(),
                        TextEntry::make('price')->numeric(),
                        TextEntry::make('total_price')->numeric(),
                        self::canSeeTheCost() ? TextEntry::make('cost_price')->numeric() : null,
                        self::canSeeTheCost() ? TextEntry::make('profit')->numeric()->color('success') : null,
                    ])))
                    ->columnSpanFull(),
                TextEntry::make('notes')
                    ->label(__('filament/resources/order.fields.notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Whether the user is allowed to see what the items cost us, and so the profit worked out from it.
     */
    private static function canSeeTheCost(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }
}
