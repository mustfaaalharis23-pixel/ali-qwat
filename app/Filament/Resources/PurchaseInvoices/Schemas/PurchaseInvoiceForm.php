<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Metrics\FormattedValue;
use App\Models\Item;
use App\Models\SpecificDescription;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PurchaseInvoiceForm
{
    /**
     * The general description and type are not chosen here: a purchase invoice is always under the purchases description.
     * Each line is an item with its boxes, box quantity, and price; the quantity is calculated by the model.
     * The expiry date is hidden from the lines for now.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/resources/invoice.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('supplier_id')
                    ->label(__('filament/resources/invoice.fields.supplier_id'))
                    ->relationship('supplier', 'name_ar')
                    ->searchable()
                    ->preload()
                    ->required(),
                DateInput::make('date')
                    ->label(__('filament/resources/invoice.fields.date'))
                    ->defaultToday()
                    ->required(),
                Repeater::make('items')
                    ->label(__('filament/resources/purchase_invoice.fields.items'))
                    ->relationship()
                    ->table([
                        TableColumn::make(__('filament/resources/purchase_invoice.fields.item'))
                            ->markAsRequired()
                            ->width('40%'),
                        TableColumn::make(__('filament/resources/invoice_item.fields.boxes_count'))
                            ->markAsRequired()
                            ->width('20%'),
                        TableColumn::make(__('filament/resources/invoice_item.fields.box_quantity'))
                            ->markAsRequired()
                            ->width('20%'),
                        TableColumn::make(__('filament/resources/invoice_item.fields.price'))
                            ->markAsRequired()
                            ->width('20%'),
                    ])
                    ->schema([
                        Select::make('specific_description_id')
                            ->label(__('filament/resources/purchase_invoice.fields.item'))
                            ->relationship(
                                'specificDescription',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->whereNotNull('item_id'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(fn (mixed $state): ?string => self::itemHint($state)),
                        TextInput::make('boxes_count')
                            ->label(__('filament/resources/invoice_item.fields.boxes_count'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('box_quantity')
                            ->label(__('filament/resources/invoice_item.fields.box_quantity'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        MoneyInput::make('price')
                            ->label(__('filament/resources/invoice_item.fields.price'))
                            ->minValue(0)
                            ->required()
                            ->helperText(fn (Get $get): ?string => self::costHint($get('specific_description_id'))),
                    ])
                    ->minItems(1)
                    ->addActionLabel(__('filament/resources/purchase_invoice.actions.add_line'))
                    ->columnSpanFull(),
                FileUpload::make('images')
                    ->label(__('filament/resources/invoice.fields.images'))
                    ->multiple()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('invoices')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('filament/resources/invoice.fields.notes'))
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * What the data entry needs to know about the chosen item, shown under its name:
     * how much of it is in the stock, how much of it was bought and did not arrive yet, and what it sells for.
     */
    private static function itemHint(mixed $descriptionId): ?string
    {
        $item = self::itemOf($descriptionId);

        if ($item === null) {
            return null;
        }

        return __('filament/resources/purchase_invoice.hints.item', [
            'available' => FormattedValue::of((float) $item->available_quantity)->number,
            'incoming' => FormattedValue::of((float) $item->incoming_quantity)->number,
            'sale_price' => FormattedValue::of((float) $item->sale_price)->number,
        ]);
    }

    /**
     * What one unit of the chosen item costs us now, and what it cost before, shown under the price.
     */
    private static function costHint(mixed $descriptionId): ?string
    {
        $item = self::itemOf($descriptionId);

        if ($item === null || ! (auth()->user()?->can('viewCostPrice', Item::class) ?? false)) {
            return null;
        }

        return __('filament/resources/purchase_invoice.hints.cost', [
            'cost_price' => FormattedValue::of((float) $item->cost_price)->number,
            'last_cost_price' => FormattedValue::of((float) $item->last_cost_price)->number,
        ]);
    }

    /**
     * The item of the chosen description, or null when no item is chosen yet.
     */
    private static function itemOf(mixed $descriptionId): ?Item
    {
        if (blank($descriptionId)) {
            return null;
        }

        return SpecificDescription::query()->find($descriptionId)?->item;
    }
}
