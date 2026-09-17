<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderPaymentType;
use App\Filament\Forms\Components\DateInput;
use App\Models\Item;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OrderForm
{
    /**
     * Each line is an item and its quantity. The price is shown from the item's sale price and is not entered,
     * because the checkout always takes it from the database.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label(__('filament/resources/order.fields.customer_id'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('payment_type')
                    ->label(__('filament/resources/order.fields.payment_type'))
                    ->options(OrderPaymentType::class)
                    ->required(),
                DateInput::make('date')
                    ->label(__('filament/resources/order.fields.date'))
                    ->defaultToday()
                    ->required(),
                Repeater::make('lines')
                    ->label(__('filament/resources/order.fields.items'))
                    ->table([
                        TableColumn::make(__('filament/resources/order.fields.item'))
                            ->markAsRequired()
                            ->width('50%'),
                        TableColumn::make(__('filament/resources/order.fields.quantity'))
                            ->markAsRequired()
                            ->width('25%'),
                        TableColumn::make(__('filament/resources/order.fields.price'))
                            ->width('25%'),
                    ])
                    ->schema([
                        Select::make('item_id')
                            ->label(__('filament/resources/order.fields.item'))
                            ->options(fn (): array => Item::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->live()
                            ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('price', Item::query()->find($state)?->sale_price))
                            ->required(),
                        TextInput::make('quantity')
                            ->label(__('filament/resources/order.fields.quantity'))
                            ->numeric()
                            ->minValue(0)
                            ->gt(0)
                            ->required(),
                        TextInput::make('price')
                            ->label(__('filament/resources/order.fields.price'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->minItems(1)
                    ->addActionLabel(__('filament/resources/order.actions.add_line'))
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('filament/resources/order.fields.notes'))
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
