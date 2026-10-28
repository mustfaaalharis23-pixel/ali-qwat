<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class OrderInfolist
{
    /**
     * The details page of an order: its information, amounts (what the customer owes written large first), lines, and notes.
     * What the order cost us and what it earned, on the order and on each line, show only to a user
     * who has the item.view_cost_price permission.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/order.sections.details'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 5])
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
                // What the customer owes is written large: the total, what was paid, and what is left, red while something is owed.
                Section::make(__('filament/resources/order.sections.amounts'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('total_amount')
                            ->label(__('filament/resources/order.fields.total_amount'))
                            ->numeric()
                            ->badge()
                            ->color('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('paid_amount')
                            ->label(__('filament/resources/order.fields.paid_amount'))
                            ->numeric()
                            ->badge()
                            ->color('success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('remaining')
                            ->label(__('filament/resources/order.fields.remaining'))
                            ->numeric()
                            ->badge()
                            ->color(fn (float $state): string => $state > 0 ? 'danger' : 'success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('profit')
                            ->label(__('filament/resources/order.fields.profit'))
                            ->numeric()
                            ->badge()
                            ->color('success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->visible(fn (): bool => self::canSeeTheCost()),
                        TextEntry::make('amount')
                            ->label(__('filament/resources/order.fields.amount'))
                            ->numeric(),
                        // What the order's discount took off its amount, on an order that has one.
                        TextEntry::make('discount')
                            ->label(__('filament/resources/order.fields.discount'))
                            ->state(fn (Order $record): float => (float) $record->amount - (float) $record->total_amount)
                            ->numeric()
                            ->color('danger')
                            ->visible(fn (Order $record): bool => (float) $record->amount - (float) $record->total_amount > 0.0001),
                        TextEntry::make('down_payment')
                            ->label(__('filament/resources/order.fields.down_payment'))
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('weekly_payment')
                            ->label(__('filament/resources/order.fields.weekly_payment'))
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('total_cost')
                            ->label(__('filament/resources/order.fields.total_cost'))
                            ->numeric()
                            ->visible(fn (): bool => self::canSeeTheCost()),
                    ]),
                Section::make(__('filament/resources/order.fields.items'))
                    ->icon(Heroicon::OutlinedQueueList)
                    ->columnSpanFull()
                    ->schema([
                        self::linesEntry(),
                    ]),
                TextEntry::make('notes')
                    ->label(__('filament/resources/order.fields.notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The order's lines as a table, with the lens power a line was sold at and the colors of its packets as badges under its item,
     * and its dozens with the packets and pieces they are under them.
     * What each line cost us and earned shows only to a user who can see the cost.
     */
    private static function linesEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->hiddenLabel()
            ->table(array_values(array_filter([
                TableColumn::make(__('filament/resources/order.fields.item'))->width('40%'),
                TableColumn::make(__('filament/resources/order.fields.quantity')),
                TableColumn::make(__('filament/resources/order.fields.price')),
                TableColumn::make(__('filament/resources/order.fields.total_price')),
                self::canSeeTheCost() ? TableColumn::make(__('filament/resources/order.fields.cost_price')) : null,
                self::canSeeTheCost() ? TableColumn::make(__('filament/resources/order.fields.profit')) : null,
            ])))
            ->schema(array_values(array_filter([
                TextEntry::make('item.name')
                    ->belowContent(fn (OrderItem $record): array => array_values(array_filter([
                        $record->lensPower === null ? null : Text::make(
                            __('filament/resources/purchase_invoice.lines_below.power', ['value' => $record->lensPower->value]),
                        )->badge()->color('info'),
                        $record->colors === null ? null : Text::make($record->colorsText())->badge()->color('gray'),
                    ]))),
                TextEntry::make('quantity')
                    ->numeric(maxDecimalPlaces: 2)
                    ->belowContent(fn (OrderItem $record): Text => Text::make(__('filament/resources/order.packets_and_pieces', [
                        'packets' => Number::format($record->packets(), maxPrecision: 2),
                        'pieces' => Number::format((float) $record->pieces),
                    ]))->size(TextSize::ExtraSmall)->color('gray')),
                TextEntry::make('price')->numeric(),
                TextEntry::make('total_price')->numeric(),
                self::canSeeTheCost() ? TextEntry::make('cost_price')->numeric() : null,
                self::canSeeTheCost() ? TextEntry::make('profit')->numeric()->color('success') : null,
            ])));
    }

    /**
     * Whether the user is allowed to see what the items cost us, and so the profit worked out from it.
     */
    private static function canSeeTheCost(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }
}
