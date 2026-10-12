<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\Item;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ItemInfolist
{
    /**
     * The item's page under its cards: the item's own information. The stock, what was sold, and the purchase prices
     * are cards above it. The cost price and the profit margin show only to a user who has the item.view_cost_price permission.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/item.sections.details'))
                    ->icon(Heroicon::OutlinedCube)
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('image')
                            ->label(__('filament/resources/item.fields.image'))
                            ->disk('public'),
                        TextEntry::make('name')
                            ->label(__('filament/resources/item.fields.name')),
                        TextEntry::make('supplier.name_ar')
                            ->label(__('filament/resources/item.fields.supplier_id'))
                            ->placeholder('—'),
                        TextEntry::make('sale_price')
                            ->label(__('filament/resources/item.fields.sale_price'))
                            ->numeric(),
                        TextEntry::make('cost_price')
                            ->label(__('filament/resources/item.fields.cost_price'))
                            ->numeric()
                            ->visible(fn (): bool => self::canSeeTheCost()),
                        TextEntry::make('profit_margin')
                            ->label(__('filament/resources/item.fields.profit_margin'))
                            ->state(fn (Item $record): float => $record->profitMargin())
                            ->numeric()
                            ->visible(fn (): bool => self::canSeeTheCost()),
                        IconEntry::make('is_active')
                            ->label(__('filament/resources/item.fields.is_active'))
                            ->boolean(),
                    ]),
                Section::make(__('filament/resources/item.fields.description'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->placeholder('—'),
                    ]),
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
