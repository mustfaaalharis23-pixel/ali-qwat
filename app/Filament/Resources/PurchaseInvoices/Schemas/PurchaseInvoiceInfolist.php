<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Filament\Resources\Containers\ContainerResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Metrics\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\CarbonInterface;
use Closure;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class PurchaseInvoiceInfolist
{
    /**
     * The details page of a purchase invoice. On wide screens it has a main column and a side column:
     * the main column holds the invoice's information, its money in yuan, its money in US dollars, and its shipping
     * (CBM and transport cost); the side column holds where the invoice is (status, stage, the awaited side) and who
     * confirmed it and when. Under both come the lines, then the images and notes when there are any.
     * The transport cost is in US dollars, like its container's price of one CBM, so it is shown apart from the amounts in yuan.
     * The amount, and each line's unit cost and price, are also shown in US dollars, as saved through the invoice's exchange rate,
     * and the transport costs as saved when the invoice was put in its container.
     * The payoffs are shown under it as a relation manager.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                self::detailsSection(),
                                self::amountsSection(),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        self::dollarsSection(),
                                        self::shippingSection(),
                                    ]),
                                self::dinarsSection(),
                            ]),
                        self::progressSection()
                            ->columnSpan(['lg' => 1]),
                    ]),
                Section::make(__('filament/resources/purchase_invoice.fields.items'))
                    ->icon(Heroicon::OutlinedQueueList)
                    ->columnSpanFull()
                    ->schema([
                        self::linesEntry(),
                    ]),
                Section::make(__('filament/resources/purchase_invoice.sections.attachments'))
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->visible(fn (Invoice $record): bool => filled($record->images) || filled($record->notes))
                    ->schema([
                        ImageEntry::make('images')
                            ->label(__('filament/resources/invoice.fields.images'))
                            ->disk('public')
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label(__('filament/resources/invoice.fields.notes'))
                            ->placeholder('—'),
                    ]),
            ]);
    }

    /**
     * Who the invoice is from and where it belongs. Whether it was split, or split into parts, shows next to the heading.
     */
    private static function detailsSection(): Section
    {
        return Section::make(__('filament/resources/purchase_invoice.sections.details'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->afterHeader(fn (Invoice $record): ?View => PurchaseInvoiceResource::splitFacts($record))
            ->columns(['default' => 1, 'sm' => 2, 'xl' => 3])
            ->schema([
                TextEntry::make('supplier.name_ar')
                    ->label(__('filament/resources/invoice.fields.supplier_id'))
                    ->weight(FontWeight::Medium),
                TextEntry::make('category.name')
                    ->label(__('filament/resources/invoice.fields.invoice_category_id'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextEntry::make('container')
                    ->label(__('filament/resources/invoice.fields.container_id'))
                    ->state(fn (Invoice $record): ?string => $record->container?->displayName())
                    ->color(fn (Invoice $record): ?string => ContainerResource::viewUrlFor($record->container) === null ? null : 'primary')
                    ->url(fn (Invoice $record): ?string => ContainerResource::viewUrlFor($record->container))
                    ->placeholder('—'),
                TextEntry::make('date')
                    ->label(__('filament/resources/invoice.fields.date'))
                    ->date('Y/m/d'),
                TextEntry::make('user.name')
                    ->label(__('filament/resources/invoice.fields.user_id')),
                TextEntry::make('splitFrom.name')
                    ->label(__('filament/resources/invoice.fields.split_from_invoice_id'))
                    ->color('primary')
                    ->url(fn (Invoice $record): ?string => $record->splitFrom === null ? null : PurchaseInvoiceResource::getUrl('view', ['record' => $record->splitFrom]))
                    ->visible(fn (Invoice $record): bool => $record->split_from_invoice_id !== null),
            ]);
    }

    /**
     * The invoice's money in yuan, written large: its amount, what was paid, and what is left. What is still owed is red.
     */
    private static function amountsSection(): Section
    {
        return Section::make(__('filament/resources/purchase_invoice.sections.amounts'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->columns(['default' => 1, 'sm' => 3])
            ->schema([
                self::moneyEntry('amount', 'primary')
                    ->label(__('filament/resources/invoice.fields.amount')),
                self::moneyEntry('paid_amount', 'success')
                    ->label(__('filament/resources/invoice.fields.paid_amount')),
                self::moneyEntry('remaining', fn (float $state): string => $state > 0 ? 'danger' : 'success')
                    ->label(__('filament/resources/invoice.fields.remaining')),
            ]);
    }

    /**
     * The amount in US dollars and the exchange rate it was worked out with.
     */
    private static function dollarsSection(): Section
    {
        return Section::make(__('filament/resources/purchase_invoice.sections.dollars'))
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->columns(['default' => 1, 'sm' => 3, 'md' => 1, 'xl' => 3])
            ->schema([
                TextEntry::make('amount_usd')
                    ->label(__('filament/resources/invoice.fields.amount_usd'))
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' '.Currency::UsDollar->symbol())
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::Bold)
                    ->placeholder('—'),
                TextEntry::make('exchange_rate')
                    ->label(__('filament/resources/invoice.fields.exchange_rate'))
                    ->numeric(maxDecimalPlaces: 5)
                    ->prefix('1 '.Currency::UsDollar->symbol().' = ')
                    ->suffix(' '.Currency::ChineseYuan->symbol())
                    ->placeholder('—'),
                // The bank's name opens its page of rates in a new tab, so the rate can be checked there.
                TextEntry::make('exchange_rate_source')
                    ->label(__('filament/resources/invoice.fields.exchange_rate_source'))
                    ->color(fn (Invoice $record): ?string => $record->exchange_rate_source?->url() === null ? null : 'primary')
                    ->url(fn (Invoice $record): ?string => $record->exchange_rate_source?->url(), shouldOpenInNewTab: true)
                    ->placeholder('—'),
            ]);
    }

    /**
     * The invoice in Iraqi dinars: its amount, its transport, the two together, and the dollar rate in dinars they were worked out with.
     * A dinar has no smaller part worth showing, so the amounts are whole.
     */
    private static function dinarsSection(): Section
    {
        return Section::make(__('filament/resources/purchase_invoice.sections.dinars'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
            ->schema([
                self::dinarEntry('amount_iqd')
                    ->label(__('filament/resources/invoice.fields.amount_iqd'))
                    ->color('primary'),
                self::dinarEntry('transport_cost_iqd')
                    ->label(__('filament/resources/invoice.fields.transport_cost_iqd'))
                    ->color('info'),
                self::dinarEntry('total_iqd')
                    ->label(__('filament/resources/invoice.fields.total_iqd'))
                    ->state(fn (Invoice $record): ?float => $record->amount_iqd === null ? null : $record->amount_iqd + (float) $record->transport_cost_iqd)
                    ->color('success'),
                TextEntry::make('dinar_rate')
                    ->label(__('filament/resources/invoice.fields.dinar_rate'))
                    ->numeric(maxDecimalPlaces: 3)
                    ->prefix('100 '.Currency::UsDollar->symbol().' = ')
                    ->suffix(' '.__('filament/resources/purchase_invoice.dinar_rate_unit'))
                    ->placeholder('—'),
            ]);
    }

    /**
     * An amount in Iraqi dinars written large and whole, a dash while there is none.
     */
    private static function dinarEntry(string $name): TextEntry
    {
        return TextEntry::make($name)
            ->numeric(maxDecimalPlaces: 0)
            ->suffix(' '.Currency::IraqiDinar->symbol())
            ->badge()
            ->weight(FontWeight::Bold)
            ->placeholder('—');
    }

    /**
     * The space the invoice takes, its price of one CBM, and what it costs to ship, in US dollars.
     */
    private static function shippingSection(): Section
    {
        return Section::make(__('filament/resources/purchase_invoice.sections.shipping'))
            ->icon(Heroicon::OutlinedTruck)
            ->columns(['default' => 1, 'sm' => 3])
            ->schema([
                TextEntry::make('cbm')
                    ->label(__('filament/resources/invoice.fields.cbm'))
                    ->numeric(maxDecimalPlaces: 3)
                    ->badge()
                    ->color('info')
                    ->weight(FontWeight::Bold),
                TextEntry::make('cbm_price')
                    ->label(__('filament/resources/invoice.fields.cbm_price'))
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' '.Currency::UsDollar->symbol())
                    ->badge()
                    ->color('info')
                    ->weight(FontWeight::Bold)
                    ->icon(fn (Invoice $record): ?Heroicon => $record->cbm_price_locked_at === null ? null : Heroicon::OutlinedLockClosed)
                    ->placeholder('—'),
                TextEntry::make('transport_cost_usd')
                    ->label(__('filament/resources/invoice.fields.transport_cost'))
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' '.Currency::UsDollar->symbol())
                    ->badge()
                    ->color('info')
                    ->weight(FontWeight::Bold)
                    ->placeholder('—'),
            ]);
    }

    /**
     * Where the invoice is: its status and stage, the side it is waiting for, and who confirmed it and when.
     * Each side's confirmation is one entry: the name, with the time written small under it.
     */
    private static function progressSection(): Section
    {
        return Section::make(__('filament/resources/purchase_invoice.sections.progress'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->columns(2)
            ->schema([
                TextEntry::make('status')
                    ->label(__('filament/resources/invoice.fields.status'))
                    ->badge(),
                TextEntry::make('stage')
                    ->label(__('filament/resources/invoice.fields.stage'))
                    ->badge(),
                TextEntry::make('awaited_confirmation')
                    ->label(__('filament/resources/invoice.fields.awaited_confirmation'))
                    ->state(fn (Invoice $record): ?string => $record->awaitedConfirmation()?->getLabel())
                    ->badge()
                    ->color('warning')
                    ->icon(Heroicon::OutlinedClock)
                    ->columnSpanFull()
                    ->visible(fn (Invoice $record): bool => $record->awaitedConfirmation() !== null),
                TextEntry::make('importerChecker.name')
                    ->label(__('filament/resources/invoice.fields.importer_checker_id'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->iconColor('success')
                    ->placeholder('—')
                    ->belowContent(fn (Invoice $record): ?Text => self::confirmedAt($record->importer_confirmed_at)),
                TextEntry::make('receiverChecker.name')
                    ->label(__('filament/resources/invoice.fields.receiver_checker_id'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->iconColor('success')
                    ->placeholder('—')
                    ->belowContent(fn (Invoice $record): ?Text => self::confirmedAt($record->receiver_confirmed_at)),
            ]);
    }

    /**
     * An amount in yuan written large in a coloured badge, like the amounts under the invoice's form.
     */
    private static function moneyEntry(string $name, string|Closure $color): TextEntry
    {
        return TextEntry::make($name)
            ->numeric()
            ->suffix(' '.Currency::ChineseYuan->symbol())
            ->badge()
            ->color($color)
            ->size(TextSize::Large)
            ->weight(FontWeight::Bold);
    }

    /**
     * The small grey time under a side's confirmation, or nothing while that side has not confirmed.
     */
    private static function confirmedAt(?CarbonInterface $confirmedAt): ?Text
    {
        return $confirmedAt === null ? null : Text::make($confirmedAt->format('Y/m/d H:i'))
            ->size(TextSize::ExtraSmall)
            ->color('gray');
    }

    /**
     * The invoice's lines as a table. Each column shows one main number, with what it is made of written small under it.
     */
    private static function linesEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->hiddenLabel()
            ->table([
                TableColumn::make(__('filament/resources/item.fields.image'))->width('88px'),
                TableColumn::make(__('filament/resources/purchase_invoice.fields.item'))->width('22%'),
                TableColumn::make(__('filament/resources/invoice_item.fields.quantity')),
                TableColumn::make(__('filament/resources/invoice_item.fields.cartons_count')),
                TableColumn::make(__('filament/resources/invoice_item.fields.cbm')),
                TableColumn::make(__('filament/resources/purchase_invoice.fields.unit_cost')),
                TableColumn::make(__('filament/resources/purchase_invoice.fields.line_total')),
                TableColumn::make(__('filament/resources/invoice.fields.transport_cost')),
                TableColumn::make(__('filament/resources/purchase_invoice.fields.final_unit_cost')),
            ])
            ->schema([
                // So whoever reads the invoice sees exactly what was bought; a click opens it full size.
                ImageEntry::make('item.image')
                    ->disk('public')
                    ->imageSize(64)
                    ->square()
                    ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 0.5rem'])
                    ->url(fn (InvoiceItem $record): ?string => filled($record->item?->image)
                        ? Storage::disk('public')->url($record->item->image)
                        : null, shouldOpenInNewTab: true)
                    ->placeholder('—'),
                TextEntry::make('specificDescription.name')
                    ->weight(FontWeight::Medium)
                    ->color(fn (InvoiceItem $record): ?string => self::itemUrl($record) === null ? null : 'primary')
                    ->url(fn (InvoiceItem $record): ?string => self::itemUrl($record), shouldOpenInNewTab: true),
                TextEntry::make('quantity')
                    ->numeric()
                    ->weight(FontWeight::Medium)
                    ->belowContent(fn (InvoiceItem $record): Text => self::below('packets', [
                        'packets' => self::number($record->boxes_count),
                        'capacity' => self::number($record->box_quantity),
                    ])),
                TextEntry::make('cartons_count')
                    ->numeric()
                    ->placeholder('—')
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->boxes_per_carton === null ? null : self::below('per_carton', [
                        'value' => self::number($record->boxes_per_carton),
                    ])),
                // A carton can be a few hundredths of a cubic metre, so its CBM keeps more decimals than the line's.
                TextEntry::make('cbm')
                    ->numeric(maxDecimalPlaces: 3)
                    ->color('info')
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->carton_cbm === null ? null : self::below('carton_cbm', [
                        'value' => self::number($record->carton_cbm, 4),
                    ])),
                TextEntry::make('unit_cost')
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' '.Currency::ChineseYuan->symbol())
                    ->placeholder('—')
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->unit_cost_usd === null ? null : self::below('usd', [
                        'value' => self::number($record->unit_cost_usd),
                    ])),
                TextEntry::make('price')
                    ->numeric()
                    ->suffix(' '.Currency::ChineseYuan->symbol())
                    ->weight(FontWeight::SemiBold)
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->price_usd === null ? null : self::below('usd', [
                        'value' => self::number($record->price_usd),
                    ])),
                // One piece can cost a fraction of a cent to ship, so it keeps more decimals than the totals.
                TextEntry::make('transport_cost_usd')
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' '.Currency::UsDollar->symbol())
                    ->color('info')
                    ->placeholder('—')
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->transport_cost_per_piece_usd === null ? null : self::below('per_piece', [
                        'value' => self::number($record->transport_cost_per_piece_usd, 4),
                    ])),
                // What one piece costs in dinars once it arrives, the item's cost; under it, its price and its transport.
                TextEntry::make('final_unit_cost_iqd')
                    ->numeric(maxDecimalPlaces: 0)
                    ->suffix(' '.Currency::IraqiDinar->symbol())
                    ->weight(FontWeight::SemiBold)
                    ->color('success')
                    ->placeholder('—')
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->final_unit_cost_iqd === null ? null : self::below('final_parts', [
                        'cost' => self::number($record->unit_cost_iqd, 0),
                        'transport' => self::number($record->transport_cost_per_piece_iqd, 0),
                    ])),
            ]);
    }

    /**
     * The small grey text under a line's number that says what the number is made of.
     *
     * @param  array<string, string>  $replace
     */
    private static function below(string $name, array $replace): Text
    {
        return Text::make(__("filament/resources/purchase_invoice.lines_below.{$name}", $replace))
            ->size(TextSize::ExtraSmall);
    }

    /**
     * A number written the way the panel writes numbers, with no decimals it does not need.
     */
    private static function number(float|int|string|null $value, int $maxDecimals = 2): string
    {
        return Number::format((float) $value, maxPrecision: $maxDecimals, locale: app()->getLocale());
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
