<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\Item;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class ItemForm
{
    /**
     * The sale price is entered here. The cost price, the profit margin, and the quantities are read only,
     * because only the system writes them: they are never changed from this screen.
     * The cost price and the profit margin show only to a user who has the item.view_cost_price permission.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/resources/item.fields.name'))
                    ->required()
                    ->maxLength(255),
                FileUpload::make('image')
                    ->label(__('filament/resources/item.fields.image'))
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('items')
                    ->required(),
                Select::make('supplier_id')
                    ->label(__('filament/resources/item.fields.supplier_id'))
                    ->relationship('supplier', 'name_ar')
                    ->searchable()
                    ->preload(),
                MoneyInput::make('sale_price')
                    ->label(__('filament/resources/item.fields.sale_price'))
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                MoneyInput::make('cost_price')
                    ->label(__('filament/resources/item.fields.cost_price'))
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (): bool => self::canSeeTheCost()),
                TextInput::make('profit_margin')
                    ->label(__('filament/resources/item.fields.profit_margin'))
                    ->formatStateUsing(fn (?Item $record): string => Number::format($record?->profitMargin() ?? 0, maxPrecision: 2))
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create')
                    ->visible(fn (): bool => self::canSeeTheCost()),
                TextInput::make('available_quantity')
                    ->label(__('filament/resources/item.fields.available_quantity'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create'),
                TextInput::make('incoming_quantity')
                    ->label(__('filament/resources/item.fields.incoming_quantity'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create'),
                Textarea::make('description')
                    ->label(__('filament/resources/item.fields.description'))
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('filament/resources/item.fields.is_active'))
                    ->default(true),
            ]);
    }

    /**
     * Whether the user is allowed to see what the items cost us.
     */
    private static function canSeeTheCost(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }
}
