<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Filament\Resources\Containers\ContainerResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Metrics\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemColor;
use App\Models\InvoiceItemGroup;
use App\Models\Item;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
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
                                self::costsSection(),
                            ]),
                        self::progressSection()
                            ->columnSpan(['lg' => 1]),
                    ]),
                Section::make(__('filament/resources/purchase_invoice.fields.items'))
                    ->description(__('filament/resources/purchase_invoice.lines_description'))
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
     * What the invoice really costs, kept to three numbers written large: its amount in US dollars, its transport, and the two
     * together in Iraqi dinars. Under each, small, what it was worked out with: the exchange rate, the CBM × its price (locked once
     * the invoice entered a container), and the amount and the transport in dinars. A dash while a rate is missing.
     * A dinar has no smaller part worth showing, so the dinars are whole.
     */
    private static function costsSection(): Section
    {
        return Section::make(__('filament/resources/purchase_invoice.sections.costs'))
            ->icon(Heroicon::OutlinedCalculator)
            ->columns(['default' => 1, 'sm' => 3])
            ->schema([
                self::costEntry('amount_usd', Currency::UsDollar, 2, 'primary')
                    ->label(__('filament/resources/invoice.fields.amount_usd'))
                    ->belowContent(fn (Invoice $record): ?Text => $record->exchange_rate === null ? null : self::costBelow('rate', [
                        'rate' => self::number($record->exchange_rate, 5),
                    ], isFormula: true)),
                self::costEntry('transport_cost_usd', Currency::UsDollar, 2, 'info')
                    ->label(__('filament/resources/invoice.fields.transport_cost'))
                    ->belowContent(fn (Invoice $record): ?Text => $record->cbm_price === null ? null : self::costBelow('shipping', [
                        'cbm' => self::number($record->cbm, 3),
                        'price' => self::number($record->cbm_price),
                    ], isFormula: true)->icon($record->cbm_price_locked_at === null ? null : Heroicon::OutlinedLockClosed)),
                self::costEntry('total_iqd', Currency::IraqiDinar, 0, 'success')
                    ->label(__('filament/resources/purchase_invoice.fields.total_iqd'))
                    ->state(fn (Invoice $record): ?float => $record->amount_iqd === null ? null : $record->amount_iqd + (float) $record->transport_cost_iqd)
                    ->belowContent(fn (Invoice $record): ?Text => $record->amount_iqd === null ? null : self::costBelow('dinars', [
                        'amount' => self::number($record->amount_iqd, 0),
                        'transport' => self::number($record->transport_cost_iqd, 0),
                    ])),
            ]);
    }

    /**
     * One of the costs, written large in a coloured badge with its currency, a dash while there is none.
     */
    private static function costEntry(string $name, Currency $currency, int $decimals, string $color): TextEntry
    {
        return TextEntry::make($name)
            ->numeric(maxDecimalPlaces: $decimals)
            ->suffix(' '.$currency->symbol())
            ->badge()
            ->color($color)
            ->size(TextSize::Large)
            ->weight(FontWeight::Bold)
            ->placeholder('—');
    }

    /**
     * The small grey line under a cost: what it was worked out with. A formula of numbers and Latin units is kept left to right,
     * so it does not read backwards in Arabic.
     *
     * @param  array<string, string>  $replace
     */
    private static function costBelow(string $name, array $replace, bool $isFormula = false): Text
    {
        $text = __("filament/resources/purchase_invoice.costs_below.{$name}", $replace);

        return Text::make($isFormula ? "\u{2066}{$text}\u{2069}" : $text)
            ->size(TextSize::ExtraSmall)
            ->color('gray');
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
     * The invoice's lines as a table. Each column shows one main number, with what it is made of written small under it,
     * and neither breaks over two lines. The item's picture sits beside its name in the same cell, with the line's groups (power, packets, and colors)
     * as badges under the name.
     */
    private static function linesEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('items')
            ->hiddenLabel()
            ->table([
                TableColumn::make(__('filament/resources/purchase_invoice.fields.item'))->width('24%'),
                TableColumn::make(__('filament/resources/invoice_item.fields.quantity')),
                TableColumn::make(__('filament/resources/invoice_item.fields.cartons_count')),
                TableColumn::make(__('filament/resources/invoice_item.fields.cbm')),
                TableColumn::make(__('filament/resources/purchase_invoice.fields.dozen_cost')),
                TableColumn::make(__('filament/resources/purchase_invoice.fields.line_total')),
                TableColumn::make(__('filament/resources/invoice.fields.transport_cost')),
                TableColumn::make(__('filament/resources/purchase_invoice.fields.final_dozen_cost')),
            ])
            ->schema([
                Flex::make([
                    // So whoever reads the invoice sees exactly what was bought; a click opens it full size.
                    // It keeps its width, so the name beside it never runs under it.
                    ImageEntry::make('item.image')
                        ->disk('public')
                        ->imageSize(56)
                        ->square()
                        ->extraAttributes(['style' => 'min-width: 56px'])
                        ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 0.5rem'])
                        ->url(fn (InvoiceItem $record): ?string => filled($record->item?->image)
                            ? Storage::disk('public')->url($record->item->image)
                            : null, shouldOpenInNewTab: true)
                        ->placeholder('—')
                        ->grow(false),
                    TextEntry::make('specificDescription.name')
                        ->weight(FontWeight::Medium)
                        ->color(fn (InvoiceItem $record): ?string => self::itemUrl($record) === null ? null : 'primary')
                        ->url(fn (InvoiceItem $record): ?string => self::itemUrl($record), shouldOpenInNewTab: true)
                        // How the line's packets are bought, a short badge for each power (its packets) and one for the colors
                        // of a packet, and a button that opens its colors and powers in full.
                        ->belowContent(fn (InvoiceItem $record): array => $record->groups->isEmpty() && $record->colors->isEmpty() ? [] : [
                            ...$record->groups
                                ->map(fn (InvoiceItemGroup $group): Text => Text::make(self::groupBadge($group))->badge()->color('info'))
                                ->all(),
                            ...($record->colorsDescription() === null ? [] : [
                                Text::make($record->colorsDescription())->badge()->color('gray')->icon(Heroicon::OutlinedSwatch),
                            ]),
                            self::groupsAction(),
                        ]),
                ])
                    ->dense()
                    ->verticallyAlignCenter(),
                TextEntry::make('quantity')
                    ->numeric()
                    ->weight(FontWeight::Medium)
                    ->extraAttributes(self::onOneLine())
                    ->belowContent(fn (InvoiceItem $record): Text => self::below('packets', [
                        'packets' => self::number($record->boxes_count),
                        'capacity' => self::number($record->pieces_per_packet),
                    ])),
                TextEntry::make('cartons_count')
                    ->numeric()
                    ->placeholder('—')
                    ->extraAttributes(self::onOneLine())
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->boxes_per_carton === null ? null : self::below('per_carton', [
                        'value' => self::number($record->boxes_per_carton),
                    ])),
                // A carton can be a few hundredths of a cubic metre, so its CBM keeps more decimals than the line's.
                TextEntry::make('cbm')
                    ->numeric(maxDecimalPlaces: 3)
                    ->color('info')
                    ->extraAttributes(self::onOneLine())
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->carton_cbm === null ? null : self::below('carton_cbm', [
                        'value' => self::number($record->carton_cbm, 4),
                    ])),
                // The costs of the line are read per dozen, as the item is: 12 × what one piece costs, which is what is typed and stored.
                TextEntry::make('unit_cost')
                    ->state(fn (InvoiceItem $record): ?float => self::perDozen($record->unit_cost))
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' '.Currency::ChineseYuan->symbol())
                    ->placeholder('—')
                    ->extraAttributes(self::onOneLine())
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->unit_cost_usd === null ? null : self::below('usd', [
                        'value' => self::number(self::perDozen($record->unit_cost_usd)),
                    ])),
                TextEntry::make('price')
                    ->numeric()
                    ->suffix(' '.Currency::ChineseYuan->symbol())
                    ->weight(FontWeight::SemiBold)
                    ->extraAttributes(self::onOneLine())
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->price_usd === null ? null : self::below('usd', [
                        'value' => self::number($record->price_usd),
                    ])),
                // A dozen can cost a few cents to ship, so it keeps more decimals than the totals.
                TextEntry::make('transport_cost_usd')
                    ->numeric(maxDecimalPlaces: 2)
                    ->suffix(' '.Currency::UsDollar->symbol())
                    ->color('info')
                    ->placeholder('—')
                    ->extraAttributes(self::onOneLine())
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->transport_cost_per_piece_usd === null ? null : self::below('per_dozen', [
                        'value' => self::number(self::perDozen($record->transport_cost_per_piece_usd), 3),
                    ])),
                // What one dozen costs in dinars once it arrives, which becomes the item's cost; under it, its price and its transport.
                TextEntry::make('final_unit_cost_iqd')
                    ->state(fn (InvoiceItem $record): ?float => self::perDozen($record->final_unit_cost_iqd))
                    ->numeric(maxDecimalPlaces: 0)
                    ->suffix(' '.Currency::IraqiDinar->symbol())
                    ->weight(FontWeight::SemiBold)
                    ->color('success')
                    ->placeholder('—')
                    ->extraAttributes(self::onOneLine())
                    ->belowContent(fn (InvoiceItem $record): ?Text => $record->final_unit_cost_iqd === null ? null : self::below('final_parts', [
                        'cost' => self::number(self::perDozen($record->unit_cost_iqd), 0),
                        'transport' => self::number(self::perDozen($record->transport_cost_per_piece_iqd), 0),
                    ])),
            ]);
    }

    /**
     * A group in a few words: its power (or none) and its packets, such as "Power 1.00 · 36 packets".
     */
    private static function groupBadge(InvoiceItemGroup $group): string
    {
        return collect([
            $group->lensPower === null
                ? __('filament/resources/lens_power.none')
                : __('filament/resources/invoice_item.group.power', ['value' => $group->lensPower->inHundreds()]),
            __('filament/resources/invoice_item.group.packets', ['packets' => self::number($group->boxes_count)]),
        ])->implode(' · ');
    }

    /**
     * Opens the line's colors and powers in a window: the colors of one packet once, with how the packet is shared between them
     * and their pieces on the whole line, then each power with its packets, pieces, and dozens. It only shows, so it has no
     * submit button.
     */
    private static function groupsAction(): Action
    {
        return Action::make('showGroups')
            ->label(__('filament/resources/purchase_invoice.actions.show_groups'))
            ->icon(Heroicon::OutlinedSwatch)
            ->link()
            ->size(Size::Small)
            ->modalHeading(fn (InvoiceItem $record): string => (string) $record->specificDescription?->name)
            ->modalDescription(fn (InvoiceItem $record): string => __('filament/resources/purchase_invoice.groups_modal.description', [
                'packet' => self::number($record->pieces_per_packet),
                'packets' => self::number($record->boxes_count),
                'pieces' => self::number($record->quantity),
            ]))
            ->modalIcon(Heroicon::OutlinedSwatch)
            ->modalWidth(Width::ExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament/resources/purchase_invoice.actions.close'))
            ->modalContent(fn (InvoiceItem $record): View => view('filament.purchase-invoices.line-groups', [
                'colors' => self::colorRows($record),
                'groups' => $record->groups->map(fn (InvoiceItemGroup $group): array => self::groupCard($group, (float) $record->pieces_per_packet))->all(),
            ]));
    }

    /**
     * The colors of one packet of the line as the window shows them: each with its pieces in a packet, on the whole line,
     * and its share of the packet.
     *
     * @return list<array{name: string, perPacket: string, pieces: string, share: float}>
     */
    private static function colorRows(InvoiceItem $line): array
    {
        $piecesPerPacket = (float) $line->pieces_per_packet;

        return $line->colors
            ->map(fn (InvoiceItemColor $color): array => [
                'name' => (string) $color->color?->name,
                'perPacket' => __('filament/resources/purchase_invoice.groups_modal.per_packet', ['value' => self::number($color->pieces)]),
                'pieces' => __('filament/resources/purchase_invoice.groups_modal.pieces', ['value' => self::number($color->pieces * (float) $line->boxes_count)]),
                'share' => $piecesPerPacket > 0 ? min(1, (float) $color->pieces / $piecesPerPacket) : 0,
            ])
            ->values()
            ->all();
    }

    /**
     * A group as the window shows it: its power, and its packets, pieces, and dozens.
     *
     * @return array{power: string, hasPower: bool, packets: string, pieces: string, dozens: string}
     */
    private static function groupCard(InvoiceItemGroup $group, float $piecesPerPacket): array
    {
        $packets = (float) $group->boxes_count;
        $pieces = $packets * $piecesPerPacket;

        return [
            'power' => $group->lensPower === null
                ? __('filament/resources/lens_power.none')
                : __('filament/resources/invoice_item.group.power', ['value' => $group->lensPower->inHundreds()]),
            'hasPower' => $group->lensPower !== null,
            'packets' => __('filament/resources/purchase_invoice.groups_modal.packets', ['value' => self::number($packets)]),
            'pieces' => __('filament/resources/purchase_invoice.groups_modal.pieces', ['value' => self::number($pieces)]),
            'dozens' => __('filament/resources/purchase_invoice.groups_modal.dozens', ['value' => self::number($pieces / Item::PIECES_IN_A_DOZEN)]),
        ];
    }

    /**
     * What a dozen comes to from what one piece costs, or null while the piece has no cost.
     */
    private static function perDozen(mixed $pieceCost): ?float
    {
        return $pieceCost === null ? null : (float) $pieceCost * Item::PIECES_IN_A_DOZEN;
    }

    /**
     * Keeps a line's number with its currency, and the small text under it, on one line each.
     *
     * @return array<string, string>
     */
    private static function onOneLine(): array
    {
        return ['style' => 'white-space: nowrap'];
    }

    /**
     * The small grey text under a line's number that says what the number is made of.
     *
     * @param  array<string, string>  $replace
     */
    private static function below(string $name, array $replace): Text
    {
        return Text::make(__("filament/resources/purchase_invoice.lines_below.{$name}", $replace))
            ->size(TextSize::ExtraSmall)
            ->extraAttributes(self::onOneLine());
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
