<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\Item;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class ItemForm
{
    /**
     * The sale price of one set and the sets in the item's packet are entered here. The cost price, the profit margin, and the quantities are read only,
     * because only the system writes them: they are never changed from this screen.
     * The cost price and the profit margin show only to a user who has the item.view_cost_price permission.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/item.sections.details'))
                    ->icon(Heroicon::OutlinedCube)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/resources/item.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('supplier_id')
                            ->label(__('filament/resources/item.fields.supplier_id'))
                            ->relationship('supplier', 'name_ar')
                            ->searchable()
                            ->preload(),
                        FileUpload::make('image')
                            ->label(__('filament/resources/item.fields.image'))
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('items')
                            ->required(),
                        // Empty for an item that is not sold by the packet.
                        // A packet holds 20 or 24 pieces, and an item's packet never changes.
                        Select::make('pieces_per_packet')
                            ->label(__('filament/resources/item.fields.pieces_per_packet'))
                            ->helperText(__('filament/resources/item.hints.pieces_per_packet'))
                            ->options(collect(Item::PACKET_SIZES)->mapWithKeys(fn (int $size): array => [
                                $size => __('filament/resources/item.packet_size', ['pieces' => $size]),
                            ])->all())
                            ->required(),
                        Textarea::make('description')
                            ->label(__('filament/resources/item.fields.description'))
                            ->rows(4),
                        Toggle::make('is_active')
                            ->label(__('filament/resources/item.fields.is_active'))
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament/resources/item.sections.prices'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // Optional: an item left without a price is saved at 0, and its price is set later.
                        MoneyInput::make('sale_price')
                            ->label(__('filament/resources/item.fields.sale_price'))
                            ->minValue(0)
                            ->dehydrateStateUsing(fn (mixed $state): float|string => blank($state) ? 0 : str_replace(',', '', (string) $state)),
                        MoneyInput::make('cost_price')
                            ->label(__('filament/resources/item.fields.cost_price'))
                            ->helperText(__('filament/resources/item.hints.cost_price'))
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
                    ]),
                Section::make(__('filament/resources/item.sections.stock'))
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->hiddenOn('create')
                    ->schema([
                        TextInput::make('available_quantity')
                            ->label(__('filament/resources/item.fields.available_quantity'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('incoming_quantity')
                            ->label(__('filament/resources/item.fields.incoming_quantity'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
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
