<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\Item;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
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

class ItemInfolist
{
    /**
     * The item's page under its cards: the item's own information, and what came in of each lens power.
     * The stock, what was sold, and the purchase prices are cards above it.
     * The cost price and the profit margin show only to a user who has the item.view_cost_price permission.
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
                        TextEntry::make('pieces_per_packet')
                            ->label(__('filament/resources/item.fields.pieces_per_packet'))
                            ->formatStateUsing(fn (mixed $state): string => __('filament/resources/item.packet_size', ['pieces' => $state]))
                            ->placeholder('—'),
                        // Under each price of one dozen, what one piece comes to: shown only, never counted.
                        TextEntry::make('sale_price')
                            ->label(__('filament/resources/item.fields.sale_price'))
                            ->numeric()
                            ->belowContent(fn (Item $record): Text => self::piecePrice((float) $record->sale_price)),
                        TextEntry::make('cost_price')
                            ->label(__('filament/resources/item.fields.cost_price'))
                            ->numeric()
                            ->belowContent(fn (Item $record): array => [
                                self::piecePrice((float) $record->cost_price),
                                Text::make(__('filament/resources/item.hints.cost_price'))->size(TextSize::ExtraSmall),
                            ])
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
                // Hidden for an item that was never bought with a power, such as sunglasses.
                Section::make(__('filament/resources/item.sections.by_power'))
                    ->description(__('filament/resources/item.sections.by_power_description'))
                    ->icon(Heroicon::OutlinedEye)
                    ->columnSpanFull()
                    ->visible(fn (Item $record): bool => $record->quantitiesByPower()->contains(fn (array $row): bool => $row['power'] !== null))
                    ->schema([
                        RepeatableEntry::make('quantities_by_power')
                            ->hiddenLabel()
                            ->state(fn (Item $record): array => self::quantitiesByPower($record))
                            ->table([
                                TableColumn::make(__('filament/resources/item.by_power.power')),
                                TableColumn::make(__('filament/resources/item.by_power.arrived')),
                                TableColumn::make(__('filament/resources/item.by_power.incoming')),
                                TableColumn::make(__('filament/resources/item.by_power.remaining')),
                            ])
                            ->schema([
                                TextEntry::make('power')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('arrived')
                                    ->placeholder('—'),
                                TextEntry::make('incoming')
                                    ->placeholder('—'),
                                TextEntry::make('remaining')
                                    ->weight(FontWeight::SemiBold)
                                    ->color('success'),
                            ]),
                    ]),
                // Hidden for an item that was never bought with colors.
                Section::make(__('filament/resources/item.sections.by_color'))
                    ->description(__('filament/resources/item.sections.by_color_description'))
                    ->icon(Heroicon::OutlinedSwatch)
                    ->columnSpanFull()
                    ->visible(fn (Item $record): bool => $record->quantitiesByColor()->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('quantities_by_color')
                            ->hiddenLabel()
                            ->state(fn (Item $record): array => self::quantitiesByColor($record))
                            ->table([
                                TableColumn::make(__('filament/resources/item.by_color.power')),
                                TableColumn::make(__('filament/resources/item.by_color.color')),
                                TableColumn::make(__('filament/resources/item.by_color.arrived')),
                                TableColumn::make(__('filament/resources/item.by_color.incoming')),
                                TableColumn::make(__('filament/resources/item.by_color.remaining')),
                            ])
                            ->schema([
                                TextEntry::make('power')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('color')
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('arrived')
                                    ->placeholder('—'),
                                TextEntry::make('incoming')
                                    ->placeholder('—'),
                                TextEntry::make('remaining')
                                    ->weight(FontWeight::SemiBold)
                                    ->color('success'),
                            ]),
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
     * The item's quantities of each power as the table writes them: the power, or "no power" for the lines without one,
     * what arrived and what is on its way as packets with their dozens, such as "100 packets (200 dozens)", or nothing when there is none,
     * and the dozens left of it after what was sold of it.
     *
     * @return list<array{power: string, arrived: ?string, incoming: ?string, remaining: string}>
     */
    private static function quantitiesByPower(Item $record): array
    {
        return $record->quantitiesByPower()
            ->map(fn (array $row): array => [
                'power' => $row['power'] ?? __('filament/resources/lens_power.none'),
                'arrived' => self::packetsAndDozens($row['arrived_packets'], $row['arrived_pieces']),
                'incoming' => self::packetsAndDozens($row['incoming_packets'], $row['incoming_pieces']),
                'remaining' => __('filament/resources/item.by_power.dozens', [
                    'dozens' => Number::format($row['remaining_pieces'] / Item::PIECES_IN_A_DOZEN, maxPrecision: 2, locale: app()->getLocale()),
                ]),
            ])
            ->all();
    }

    /**
     * The item's pieces of each color at each power as the table writes them: the power, or "no power", the color,
     * what arrived and what is on its way in pieces, or nothing when there is none, and the pieces left after what was sold.
     *
     * @return list<array{power: string, color: ?string, arrived: ?string, incoming: ?string, remaining: string}>
     */
    private static function quantitiesByColor(Item $record): array
    {
        $pieces = fn (float $pieces): string => __('filament/resources/item.by_color.pieces', [
            'pieces' => Number::format($pieces, maxPrecision: 2, locale: app()->getLocale()),
        ]);

        return $record->quantitiesByColor()
            ->map(fn (array $row): array => [
                'power' => $row['power'] ?? __('filament/resources/lens_power.none'),
                'color' => $row['color'],
                'arrived' => $row['arrived_pieces'] > 0 ? $pieces($row['arrived_pieces']) : null,
                'incoming' => $row['incoming_pieces'] > 0 ? $pieces($row['incoming_pieces']) : null,
                'remaining' => $pieces($row['remaining_pieces']),
            ])
            ->all();
    }

    /**
     * Packets with their dozens, written the way the panel writes numbers, or null when there are no pieces.
     */
    private static function packetsAndDozens(float $packets, float $pieces): ?string
    {
        if ($pieces <= 0) {
            return null;
        }

        return __('filament/resources/item.by_power.packets_and_dozens', [
            'packets' => Number::format($packets, maxPrecision: 2, locale: app()->getLocale()),
            'dozens' => Number::format($pieces / Item::PIECES_IN_A_DOZEN, maxPrecision: 2, locale: app()->getLocale()),
        ]);
    }

    /**
     * What one piece comes to at this price of one dozen, as a small line under the price.
     */
    private static function piecePrice(float $dozenPrice): Text
    {
        return Text::make(__('filament/resources/item.hints.piece_price', [
            'value' => Number::format(Item::piecePrice($dozenPrice), maxPrecision: 0, locale: app()->getLocale()),
        ]))->size(TextSize::ExtraSmall)->color('gray');
    }

    /**
     * Whether the user is allowed to see what the items cost us.
     */
    private static function canSeeTheCost(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }
}
