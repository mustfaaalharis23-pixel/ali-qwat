<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Enums\ExchangeRateSource;
use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\Colors\ColorResource;
use App\Filament\Resources\InvoiceCategories\InvoiceCategoryResource;
use App\Filament\Resources\LensPowers\LensPowerResource;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Color;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\SpecificDescription;
use App\Models\Supplier;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class PurchaseInvoiceForm
{
    /**
     * A CBM is shown with up to three decimals, as the packing lists write it.
     */
    private const int CBM_DECIMALS = 3;

    /**
     * The dollar rate in dinars a new invoice starts with, as the market writes it: 100 $ = 170 thousand dinars.
     */
    private const int DEFAULT_DINAR_RATE = 170;

    /**
     * The general description and type are not chosen here: a purchase invoice is always under the purchases description.
     * The name is not typed either: the model names the invoice after its supplier and the supplier's counter.
     * Each line is an item with its whole packets (boxes_count), the pieces in one packet (pieces_per_packet, the item's, filled and locked
     * when it is chosen), packets per carton, carton volume (CBM), and the unit cost of one piece;
     * the quantity in pieces (packets × pieces per packet), the cartons (packets ÷ packets per carton, rounded up) and the CBM (cartons × carton volume) are calculated by the model, and the line is saved with its unit cost as typed and its total price (its pieces × the unit cost).
     * The invoice's price of one CBM, in US dollars, gives the transport cost of every line, and its dollar rate in dinars
     * turns the dollar costs into dinars, up to the final cost of one piece, and of one dozen.
     * The expiry date is hidden from the lines for now.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_id')
                    ->label(__('filament/resources/invoice.fields.supplier_id'))
                    ->relationship('supplier', 'name_ar')
                    ->searchable()
                    ->preload()
                    // Live, so the supplier's debt under the initial payment follows the choice.
                    ->live()
                    ->required(),
                Select::make('invoice_category_id')
                    ->label(__('filament/resources/invoice.fields.invoice_category_id'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    // A new category is added right here, without leaving the invoice.
                    ->createOptionForm([
                        InvoiceCategoryResource::nameInput(),
                    ])
                    ->required(),
                DateInput::make('date')
                    ->label(__('filament/resources/invoice.fields.date'))
                    ->defaultToday()
                    ->required(),
                // The yuan in one US dollar. The create page fills it from the Agricultural Bank of China, or China Merchants Bank
                // when the first does not answer, and it is typed by hand when neither does. Changing it by hand makes its source manual.
                // It is kept as it was on the day the invoice was made, so it cannot be changed after.
                TextInput::make('exchange_rate')
                    ->label(__('filament/resources/invoice.fields.exchange_rate'))
                    ->prefix('1 '.Currency::UsDollar->symbol().' =')
                    ->suffix(Currency::ChineseYuan->symbol())
                    ->numeric()
                    ->rule('gt:0')
                    // So the dollar amounts of the lines and the invoice follow the typing.
                    ->live(debounce: 500)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->disabled(fn (string $operation): bool => $operation !== 'create')
                    ->afterStateUpdated(fn (Set $set) => $set('exchange_rate_source', ExchangeRateSource::Manual->value))
                    ->helperText(fn (Get $get): ?HtmlString => self::exchangeRateSourceHint($get('exchange_rate_source'))),
                Hidden::make('exchange_rate_source')
                    ->default(ExchangeRateSource::Manual->value)
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                // Written as the market writes it, in thousands of dinars for 100 dollars, and it can be changed at any time.
                TextInput::make('dinar_rate')
                    ->label(__('filament/resources/invoice.fields.dinar_rate'))
                    ->prefix('100 '.Currency::UsDollar->symbol().' =')
                    ->suffix(__('filament/resources/purchase_invoice.dinar_rate_unit'))
                    ->numeric()
                    ->rule('gt:0')
                    ->default(self::DEFAULT_DINAR_RATE)
                    // So the dinar amounts of the lines and the invoice follow the typing.
                    ->live(debounce: 500)
                    ->required()
                    ->helperText(fn (Get $get): string => __('filament/resources/purchase_invoice.dinar_rate_hint', [
                        'value' => Number::format(self::dinarsPerDollar($get('dinar_rate')) ?? 0, locale: app()->getLocale()),
                    ])),
                // Typed by hand, and changed until the invoice first enters a container; from then on it is locked for good.
                MoneyInput::make('cbm_price')
                    ->label(__('filament/resources/invoice.fields.cbm_price'))
                    ->suffix(Currency::UsDollar->symbol())
                    ->minValue(0)
                    // So the transport costs under the lines follow the typing.
                    ->live(debounce: 500)
                    ->required(fn (?Invoice $record): bool => ! self::isCbmPriceLocked($record))
                    ->disabled(fn (?Invoice $record): bool => self::isCbmPriceLocked($record))
                    ->helperText(fn (?Invoice $record): string => self::isCbmPriceLocked($record)
                        ? __('filament/resources/purchase_invoice.cbm_price_locked_hint')
                        : __('filament/resources/purchase_invoice.cbm_price_hint')),
                Repeater::make('items')
                    ->label(__('filament/resources/purchase_invoice.fields.items'))
                    ->relationship()
                    ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::withUnitCost($data))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::withLinePrice($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::withLinePrice($data))
                    // A card per line: the item on top, then its packing, its specs (its packets in groups of a lens power and colors),
                    // and its cost, each in its own group. The specs come after the packing, because they spread the packets typed there.
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
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                $piecesPerPacket = self::itemOf($state)?->pieces_per_packet;

                                if ($piecesPerPacket !== null) {
                                    $set('pieces_per_packet', $piecesPerPacket);
                                    self::syncPacketsWithSets($get, $set);
                                }
                            })
                            ->helperText(fn (mixed $state): ?View => self::itemHint($state)),
                        Fieldset::make(__('filament/resources/purchase_invoice.sections.line_packing'))
                            ->columns(5)
                            ->schema([
                                // The line is typed in sets (a set is a dozen, 12 pieces). It is not stored: the packets are,
                                // and they follow from the sets and the packet, so the sets of a saved line come back from its packets.
                                // Live, so the line total under the unit cost follows the typing.
                                TextInput::make('sets')
                                    ->label(__('filament/resources/invoice_item.fields.sets'))
                                    ->numeric()
                                    ->rule('gt:0')
                                    ->required(fn (Get $get): bool => blank($get('boxes_count')))
                                    ->dehydrated(false)
                                    ->live(debounce: 500)
                                    ->afterStateHydrated(function (TextInput $component, Get $get): void {
                                        $component->state(self::setsOf($get('boxes_count'), $get('pieces_per_packet')));
                                    })
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::syncPacketsWithSets($get, $set);
                                    })
                                    ->rules([
                                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                            self::ensureTheSetsMakeWholePackets($value, $get('pieces_per_packet'), $fail);
                                        },
                                    ]),
                                // The item's packet, 20 or 24 pieces: it is filled from the item and locked, because an item's packet never changes.
                                TextInput::make('pieces_per_packet')
                                    ->label(__('filament/resources/invoice_item.fields.pieces_per_packet'))
                                    ->integer()
                                    ->minValue(0)
                                    ->live(debounce: 500)
                                    ->required()
                                    ->disabled(fn (Get $get): bool => self::itemOf($get('specific_description_id'))?->pieces_per_packet !== null)
                                    ->dehydrated()
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::syncPacketsWithSets($get, $set);
                                    }),
                                // Read only for now: the sets × 12 ÷ the packet. Not cast to a whole number, so sets that do not make
                                // whole packets show it ("12.6") beside their error.
                                TextInput::make('boxes_count')
                                    ->label(__('filament/resources/invoice_item.fields.boxes_count'))
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                                TextInput::make('boxes_per_carton')
                                    ->label(__('filament/resources/invoice_item.fields.boxes_per_carton'))
                                    ->numeric()
                                    ->rule('gt:0')
                                    ->live(debounce: 500)
                                    ->required()
                                    ->helperText(fn (Get $get): ?View => self::cartonsHint($get)),
                                TextInput::make('carton_cbm')
                                    ->label(__('filament/resources/invoice_item.fields.carton_cbm'))
                                    ->numeric()
                                    ->rule('gt:0')
                                    ->live(debounce: 500)
                                    ->required()
                                    ->helperText(fn (Get $get): ?View => self::cbmHint($get)),
                            ]),
                        Fieldset::make(__('filament/resources/purchase_invoice.sections.line_specs'))
                            ->columns(1)
                            ->schema([
                                self::colorsRepeater(),
                                self::groupsRepeater(),
                            ]),
                        Fieldset::make(__('filament/resources/purchase_invoice.sections.line_cost'))
                            ->columns(4)
                            ->schema([
                                MoneyInput::make('unit_cost')
                                    ->label(__('filament/resources/purchase_invoice.fields.unit_cost'))
                                    ->suffix(Currency::ChineseYuan->symbol())
                                    ->minValue(0)
                                    // So the amounts below follow the typing instead of waiting for a save.
                                    ->live(debounce: 500)
                                    ->required()
                                    ->helperText(fn (Get $get): ?View => self::costHint($get))
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->minItems(1)
                    ->addActionLabel(__('filament/resources/purchase_invoice.actions.add_line'))
                    ->columnSpanFull(),
                // All six are read only: the model owns them. The amount is the sum of the lines, what is paid comes
                // from the payoffs, the rest is the supplier's debt, and the CBM is the space the invoice takes.
                // The amount in dollars is the amount ÷ the exchange rate, and in dinars that × the dollar rate in dinars, a dash until the rates are typed.
                Section::make(__('filament/resources/purchase_invoice.sections.amounts'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->columns(6)
                    ->schema([
                        TextEntry::make('amount_preview')
                            ->label(__('filament/resources/invoice.fields.amount'))
                            ->state(fn (Get $get): string => self::money(self::linesTotal($get('items'))))
                            ->badge()
                            ->color('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('amount_usd_preview')
                            ->label(__('filament/resources/invoice.fields.amount_usd'))
                            ->state(fn (Get $get): string => self::dollars(self::linesTotal($get('items')), $get('exchange_rate')))
                            ->badge()
                            ->color('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('amount_iqd_preview')
                            ->label(__('filament/resources/invoice.fields.amount_iqd'))
                            ->state(fn (Get $get): string => self::dinars(
                                self::linesTotal($get('items')),
                                $get('exchange_rate'),
                                $get('dinar_rate'),
                            ))
                            ->badge()
                            ->color('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('paid_preview')
                            ->label(__('filament/resources/invoice.fields.paid_amount'))
                            ->state(fn (?Invoice $record): string => self::money((float) ($record?->paid_amount ?? 0)))
                            ->badge()
                            ->color('success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('remaining_preview')
                            ->label(__('filament/resources/invoice.fields.remaining'))
                            ->state(fn (Get $get, ?Invoice $record): string => self::money(
                                self::linesTotal($get('items')) - (float) ($record?->paid_amount ?? 0),
                            ))
                            ->badge()
                            // What is still owed is red, and nothing owed is green.
                            ->color(fn (Get $get, ?Invoice $record): string => self::linesTotal($get('items'))
                                - (float) ($record?->paid_amount ?? 0) > 0 ? 'danger' : 'success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('cbm_preview')
                            ->label(__('filament/resources/invoice.fields.cbm'))
                            ->state(fn (Get $get): string => self::cbm(self::linesCbm($get('items'))))
                            ->badge()
                            ->color('info')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                    ])
                    ->columnSpanFull(),
                // Optional, and only while the invoice is born, right under the amounts it changes: from then on the
                // payoffs of the invoice are added from its own page, and this one becomes the first of them.
                MoneyInput::make('initial_payment')
                    ->label(__('filament/resources/purchase_invoice.fields.initial_payment'))
                    ->suffix(Currency::ChineseYuan->symbol())
                    ->live(debounce: 500)
                    // The figures it changes first, then the note on what it does.
                    ->belowContent(fn (Get $get): array => [
                        self::initialPaymentHint($get),
                        Callout::make(__('filament/resources/purchase_invoice.initial_payment_note.heading'))
                            ->description(__('filament/resources/purchase_invoice.initial_payment_note.description'))
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->info(),
                    ])
                    ->minValue(0)
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if (blank($value)) {
                            return;
                        }

                        // The payoffs of an invoice never add up to more than it, so it is caught
                        // here instead of failing after the invoice is already saved.
                        if ((float) str_replace(',', '', (string) $value) > self::linesTotal($get('items'))) {
                            $fail(__('filament/resources/purchase_invoice.validation.initial_payment_above_amount'));
                        }
                    })
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
     * Under the exchange rate: where it came from, the bank's name as a link to its page of rates so the user can check it there,
     * or that it was typed by hand.
     */
    private static function exchangeRateSourceHint(mixed $source): ?HtmlString
    {
        $source = $source instanceof ExchangeRateSource ? $source : ExchangeRateSource::tryFrom((string) $source);

        if ($source === null) {
            return null;
        }

        $name = e($source->getLabel());
        $url = $source->url();
        $value = $url === null
            ? $name
            : '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline dark:text-primary-400">'.$name.' ↗</a>';

        return new HtmlString(__('filament/resources/purchase_invoice.exchange_rate_source_hint', ['source' => $value]));
    }

    /**
     * What the data entry needs to know about the chosen item, shown under its name one per row:
     * how much of it is in the stock (green, or red when there is none), how much of it was bought and
     * did not arrive yet (blue when there is some), and what it sells for.
     */
    private static function itemHint(mixed $descriptionId): ?View
    {
        $item = self::itemOf($descriptionId);

        if ($item === null) {
            return null;
        }

        $available = (float) $item->available_quantity;
        $incoming = (float) $item->incoming_quantity;

        return self::facts([
            self::fact('available', $available, $available > 0 ? 'success' : 'danger', Heroicon::OutlinedCube),
            self::fact('incoming', $incoming, $incoming > 0 ? 'info' : 'gray', Heroicon::OutlinedTruck),
            self::fact('sale_price', (float) $item->sale_price, 'primary', Heroicon::OutlinedTag),
        ]);
    }

    /**
     * Under the unit cost of a piece, kept to four chips: the cost of a dozen (12 × the piece) and the line's total price as it is typed
     * (packets × pieces per packet × the piece's cost), a dash until the line has a quantity and a unit cost, the final cost of one dozen
     * in dinars (12 × the piece's cost + its transport) once the invoice has an exchange rate and a dollar rate in dinars, and what one
     * dozen of the item costs now. The item's cost shows only to a user who can see the cost: red when this line's final dozen is dearer,
     * green when it is cheaper.
     */
    private static function costHint(Get $get): View
    {
        $total = self::lineTotal($get('boxes_count'), $get('pieces_per_packet'), $get('unit_cost'));
        $dozenCost = self::number($get('unit_cost')) * Item::PIECES_IN_A_DOZEN;
        $facts = [
            self::fact('dozen_cost', $dozenCost > 0 ? $dozenCost : null, 'primary', Heroicon::OutlinedSquares2x2),
            self::fact('line_total', $total > 0 ? $total : null, 'primary', Heroicon::OutlinedCalculator),
        ];

        $rate = self::number($get('../../exchange_rate'));
        $dinarsPerDollar = self::dinarsPerDollar($get('../../dinar_rate'));
        $finalUnitCost = null;

        if ($rate > 0 && $dinarsPerDollar !== null) {
            $unitCost = self::number($get('unit_cost'));
            $quantity = (float) $get('boxes_count') * (float) $get('pieces_per_packet');
            $cbm = self::lineCbm(self::cartonsCount($get('boxes_count'), $get('boxes_per_carton')), $get('carton_cbm'));
            $transportPerPiece = $quantity > 0 ? $cbm * self::number($get('../../cbm_price')) / $quantity : 0.0;
            $finalUnitCost = $unitCost > 0 ? ($unitCost / $rate + $transportPerPiece) * $dinarsPerDollar : null;

            // A dinar has no smaller part worth showing, so the amount is whole.
            $facts[] = self::fact('final_dozen_cost_iqd', $finalUnitCost === null ? null : $finalUnitCost * Item::PIECES_IN_A_DOZEN, 'primary', Heroicon::OutlinedCheckBadge, 0);
        }

        $item = self::itemOf($get('specific_description_id'));

        if ($item !== null && (auth()->user()?->can('viewCostPrice', Item::class) ?? false)) {
            $previous = (float) $item->cost_price;
            $current = $finalUnitCost === null ? 0.0 : $finalUnitCost * Item::PIECES_IN_A_DOZEN;

            $facts[] = self::fact('previous_unit_cost', $previous, ...match (true) {
                $previous > 0 && $current > $previous + 0.0001 => ['danger', Heroicon::OutlinedArrowTrendingUp],
                $previous > 0 && $current > 0 && $current < $previous - 0.0001 => ['success', Heroicon::OutlinedArrowTrendingDown],
                default => ['gray', Heroicon::OutlinedClock],
            });
        }

        return self::facts($facts);
    }

    /**
     * The space the line takes (cartons × the CBM of one carton), shown under the carton CBM once both are typed.
     * Once the invoice has its price of one CBM, also the line's transport cost in US dollars (its CBM × that price)
     * and the transport cost of one piece (the line's ÷ its pieces), following the typing.
     */
    private static function cbmHint(Get $get): ?View
    {
        $cbm = self::lineCbm(self::cartonsCount($get('boxes_count'), $get('boxes_per_carton')), $get('carton_cbm'));

        if ($cbm <= 0) {
            return null;
        }

        $facts = [self::fact('line_cbm', $cbm, 'info', Heroicon::OutlinedCube, self::CBM_DECIMALS)];

        if (filled($get('../../cbm_price'))) {
            $transportCost = $cbm * self::number($get('../../cbm_price'));
            $quantity = (float) $get('boxes_count') * (float) $get('pieces_per_packet');

            $facts[] = self::fact('transport_cost', $transportCost, 'info', Heroicon::OutlinedTruck);
            // One piece can cost a fraction of a cent to ship, so it keeps more decimals.
            $facts[] = self::fact('transport_cost_per_piece', $quantity > 0 ? $transportCost / $quantity : null, 'info', Heroicon::OutlinedCurrencyDollar, 4);
        }

        return self::facts($facts);
    }

    /**
     * The colors in one packet of the line, optional, chosen once for all its packets whatever their power: a packet of 24 pieces
     * can hold 12 red pieces and 12 black. Their pieces fill the packet. What is left of the packet shows while they are typed,
     * and once the colors fill it there is nothing left to add. A new color can be added from the list by whoever may add one.
     */
    private static function colorsRepeater(): Repeater
    {
        return Repeater::make('colors')
            ->label(__('filament/resources/invoice_item.fields.colors'))
            ->helperText(fn (Get $get, ?array $state): View => self::colorsLeftHint(self::number($get('pieces_per_packet')), $state))
            ->relationship()
            ->table([
                TableColumn::make(__('filament/resources/invoice_item.fields.color_id'))
                    ->markAsRequired()
                    ->width('60%'),
                TableColumn::make(__('filament/resources/invoice_item.fields.pieces'))
                    ->markAsRequired()
                    ->width('40%'),
            ])
            ->schema([
                Select::make('color_id')
                    ->label(__('filament/resources/invoice_item.fields.color_id'))
                    ->options(fn (): array => Color::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->distinct()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->required()
                    ->createOptionForm(fn (Schema $schema): Schema => ColorResource::form($schema))
                    ->createOptionUsing(fn (array $data): int => Color::query()->create($data)->getKey())
                    ->createOptionAction(fn (Action $action): Action => $action->visible(fn (): bool => auth()->user()?->can('create', Color::class) ?? false)),
                TextInput::make('pieces')
                    ->label(__('filament/resources/invoice_item.fields.pieces'))
                    ->integer()
                    ->rule('gt:0')
                    ->required()
                    ->live(debounce: 500),
            ])
            ->live()
            ->addable(fn (Get $get, ?array $state): bool => self::number($get('pieces_per_packet')) <= 0
                || self::colorPieces($state) < self::number($get('pieces_per_packet')) - 0.0001)
            ->rules([
                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    self::ensureTheColorsFillThePacket($value, $get('pieces_per_packet'), $fail);
                },
            ])
            ->defaultItems(0)
            ->addActionLabel(__('filament/resources/purchase_invoice.actions.add_color'));
    }

    /**
     * The line's groups, optional: glasses without a power, such as sunglasses, are bought without any. With groups, their packets
     * add up to the line's packets, each group some packets at one power (or without one).
     *
     * The groups are listed as one row each, with the power in bold (grey without one) and the packets, and a group is added and
     * edited in a modal: its power and its packets, with what is left of the line's.
     */
    private static function groupsRepeater(): Repeater
    {
        return Repeater::make('groups')
            ->label(__('filament/resources/invoice_item.fields.groups'))
            ->helperText(fn (Get $get): View|string => self::groupsHint($get('groups'), $get('boxes_count')))
            ->relationship()
            ->table([
                TableColumn::make(__('filament/resources/invoice_item.fields.lens_power_id'))->width('50%'),
                TableColumn::make(__('filament/resources/invoice_item.fields.boxes_count')),
            ])
            ->schema([
                Hidden::make('lens_power_id'),
                Hidden::make('boxes_count'),
                Text::make(fn (Get $get): string => self::powerText($get('lens_power_id')))
                    ->weight(FontWeight::Bold)
                    ->color(fn (Get $get): string => blank($get('lens_power_id')) ? 'gray' : 'neutral'),
                Text::make(fn (Get $get): string => __('filament/resources/invoice_item.group.packets', ['packets' => self::count($get('boxes_count'))]))
                    ->weight(FontWeight::Medium)
                    ->color('neutral'),
            ])
            ->rules([
                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    self::ensureTheGroupsAddUp($value, $get('boxes_count'), $fail);
                },
            ])
            ->defaultItems(0)
            ->addActionLabel(__('filament/resources/purchase_invoice.actions.add_group'))
            ->addAction(fn (Action $action): Action => self::groupAction($action, isNew: true)->icon(Heroicon::OutlinedPlus))
            ->extraItemActions([
                self::groupAction(Action::make('editGroup'), isNew: false)
                    ->label(__('filament/resources/purchase_invoice.actions.edit_group'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->iconButton()
                    ->color('gray'),
            ]);
    }

    /**
     * The modal that adds a group, or edits the one it is called on, and writes the group into the line's groups.
     */
    private static function groupAction(Action $action, bool $isNew): Action
    {
        return $action
            ->modalHeading(__('filament/resources/purchase_invoice.actions.'.($isNew ? 'add_group' : 'edit_group')))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('filament/resources/purchase_invoice.actions.save_group'))
            ->fillForm(fn (array $arguments, Repeater $component): array => $isNew ? [] : self::groupFormData($component->getRawItemState($arguments['item'])))
            ->schema(fn (array $arguments, Repeater $component): array => self::groupSchema($component, $isNew ? null : (string) $arguments['item']))
            ->action(function (array $data, array $arguments, Repeater $component) use ($isNew): void {
                $items = $component->getRawState() ?? [];
                $key = $isNew ? ($component->generateUuid() ?? count($items)) : $arguments['item'];

                $items[$key] = [
                    ...($items[$key] ?? []),
                    'lens_power_id' => $data['lens_power_id'] ?? null,
                    'boxes_count' => $data['boxes_count'] ?? null,
                ];

                $component->rawState($items);
                $component->callAfterStateUpdated();
            });
    }

    /**
     * The fields of the group modal: its power and its packets, which may not pass what is left of the line's packets after the
     * other groups, shown while they are typed. A new power can be added from its list by whoever may add one.
     *
     * @return array<int, Component>
     */
    private static function groupSchema(Repeater $groups, ?string $editedItem): array
    {
        $line = $groups->makeGetUtility();
        $packets = self::number($line('boxes_count'));
        $otherPackets = collect($groups->getRawState() ?? [])
            ->reject(fn (mixed $group, int|string $key): bool => (string) $key === $editedItem)
            ->sum(fn (mixed $group): float => self::number(is_array($group) ? ($group['boxes_count'] ?? 0) : 0));
        $left = max(0, $packets - $otherPackets);

        return [
            Select::make('lens_power_id')
                ->label(__('filament/resources/invoice_item.fields.lens_power_id'))
                ->options(fn (): array => LensPower::query()->orderBy('value')->pluck('value', 'id')->all())
                ->placeholder(__('filament/resources/lens_power.none'))
                ->searchable()
                ->createOptionForm(fn (Schema $schema): Schema => LensPowerResource::form($schema))
                ->createOptionUsing(fn (array $data): int => LensPower::query()->create($data)->getKey())
                ->createOptionAction(fn (Action $action): Action => $action->visible(fn (): bool => auth()->user()?->can('create', LensPower::class) ?? false)),
            TextInput::make('boxes_count')
                ->label(__('filament/resources/invoice_item.fields.boxes_count'))
                ->integer()
                ->rule('gt:0')
                ->required()
                ->live(debounce: 300)
                ->helperText(fn (mixed $state): View => self::facts([self::leftFact($left - self::number($state), 'packets', $packets)]))
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($packets, $left): void {
                        if ($packets <= 0) {
                            $fail(__('invoices.validation.line_sets_first'));
                        } elseif (self::number($value) > $left + 0.0001) {
                            $fail(__('invoices.validation.group_more_than_left', ['left' => self::count($left)]));
                        }
                    },
                ]),
        ];
    }

    /**
     * A row of the groups as the modal starts from it: its power and its packets.
     *
     * @param  array<string, mixed>  $group
     * @return array{lens_power_id: mixed, boxes_count: mixed}
     */
    private static function groupFormData(array $group): array
    {
        return [
            'lens_power_id' => $group['lens_power_id'] ?? null,
            'boxes_count' => $group['boxes_count'] ?? null,
        ];
    }

    /**
     * Under the groups: how many of the line's packets are in groups and what is left, or the explanation while there is none.
     */
    private static function groupsHint(mixed $groups, mixed $boxesCount): View|string
    {
        $packets = self::number($boxesCount);
        $spread = collect(is_array($groups) ? $groups : [])
            ->sum(fn (mixed $group): float => self::number(is_array($group) ? ($group['boxes_count'] ?? 0) : 0));

        if ($spread <= 0 || $packets <= 0) {
            return __('filament/resources/purchase_invoice.groups_hint');
        }

        return self::facts([
            self::fact('groups_packets', $spread, 'info', Heroicon::OutlinedSquares2x2, replace: ['total' => self::count($packets)]),
            self::leftFact($packets - $spread, 'packets'),
        ]);
    }

    /**
     * Under the line's colors, following the typing: what is left of the packet without a color, out of the packet size.
     *
     * @param  array<array-key, mixed>|null  $colors
     */
    private static function colorsLeftHint(float $piecesPerPacket, ?array $colors): View
    {
        return self::facts([self::leftFact($piecesPerPacket - self::colorPieces($colors), 'colors', $piecesPerPacket)]);
    }

    /**
     * What is left as a chip: still left in amber (out of the total when one is given), nothing left in green, or too many in red.
     *
     * @return array{text: string, tooltip: string, color: string, icon: Heroicon}
     */
    private static function leftFact(float $left, string $of, ?float $total = null): array
    {
        return match (true) {
            $left > 0.0001 && $total !== null => self::fact("{$of}_left_of", $left, 'warning', Heroicon::OutlinedExclamationCircle, replace: ['total' => self::count($total)]),
            $left > 0.0001 => self::fact("{$of}_left", $left, 'warning', Heroicon::OutlinedExclamationCircle),
            $left < -0.0001 => self::fact("{$of}_over", -$left, 'danger', Heroicon::OutlinedXCircle),
            default => self::fact("{$of}_full", null, 'success', Heroicon::OutlinedCheckCircle),
        };
    }

    /**
     * The pieces of the given colors in one packet, together.
     *
     * @param  array<array-key, mixed>|null  $colors
     */
    private static function colorPieces(?array $colors): float
    {
        return collect($colors ?? [])->sum(fn (mixed $color): float => self::number(is_array($color) ? ($color['pieces'] ?? 0) : 0));
    }

    /**
     * The power of a group row, or "no power".
     */
    private static function powerText(mixed $lensPowerId): string
    {
        return blank($lensPowerId)
            ? __('filament/resources/lens_power.none')
            : (string) (LensPower::withTrashed()->find($lensPowerId)?->value ?? '—');
    }

    /**
     * The sets (dozens of 12 pieces) that packets of the given packet make, or null without packets or a packet.
     */
    private static function setsOf(mixed $packets, mixed $piecesPerPacket): ?float
    {
        $packets = self::number($packets);
        $piecesPerPacket = self::number($piecesPerPacket);

        return $packets > 0 && $piecesPerPacket > 0 ? round($packets * $piecesPerPacket / Item::PIECES_IN_A_DOZEN, 2) : null;
    }

    /**
     * The packets that the given sets make in the given packet (sets × 12 ÷ the packet), whole when they are, or null without
     * sets or a packet.
     */
    private static function packetsOf(mixed $sets, mixed $piecesPerPacket): int|float|null
    {
        $sets = self::number($sets);
        $piecesPerPacket = self::number($piecesPerPacket);

        if ($sets <= 0 || $piecesPerPacket <= 0) {
            return null;
        }

        $packets = $sets * Item::PIECES_IN_A_DOZEN / $piecesPerPacket;

        return abs($packets - round($packets)) <= 0.0001 ? (int) round($packets) : round($packets, 2);
    }

    /**
     * Writes the line's packets from its typed sets and its packet, once both are there.
     */
    private static function syncPacketsWithSets(Get $get, Set $set): void
    {
        $packets = self::packetsOf($get('sets'), $get('pieces_per_packet'));

        if ($packets !== null) {
            $set('boxes_count', $packets);
        }
    }

    /**
     * The typed sets make whole packets of the line's packet: 20 sets of a packet of 24 pieces are 10 packets, but 21 sets of a
     * packet of 20 are 12.6, which is refused. Nothing is checked until both are there.
     */
    private static function ensureTheSetsMakeWholePackets(mixed $sets, mixed $piecesPerPacket, Closure $fail): void
    {
        $packets = self::packetsOf($sets, $piecesPerPacket);

        if (is_float($packets)) {
            $fail(__('invoices.validation.sets_not_whole_packets', [
                'sets' => self::count($sets),
                'packet' => self::count($piecesPerPacket),
                'packets' => self::count($packets),
            ]));
        }
    }

    /**
     * A count written the way the panel writes numbers.
     */
    private static function count(mixed $value): string
    {
        return (string) Number::format(self::number($value), maxPrecision: 2, locale: app()->getLocale());
    }

    /**
     * The packets of a line's groups add up to the line's packets, so no packet is left out of a group and none is counted twice.
     * A line without groups is bought without a power and without colors, so it passes.
     */
    private static function ensureTheGroupsAddUp(mixed $groups, mixed $boxesCount, Closure $fail): void
    {
        if (! is_array($groups) || $groups === []) {
            return;
        }

        $spread = collect($groups)->sum(fn (mixed $group): float => self::number(is_array($group) ? ($group['boxes_count'] ?? 0) : 0));
        $packets = self::number($boxesCount);

        if (abs($spread - $packets) > 0.0001) {
            $fail(__('invoices.validation.groups_must_add_up', [
                'spread' => Number::format($spread, maxPrecision: 2, locale: app()->getLocale()),
                'packets' => Number::format($packets, maxPrecision: 2, locale: app()->getLocale()),
            ]));
        }
    }

    /**
     * The pieces of a group's colors fill one packet exactly: the line's pieces in a packet,
     * so no piece is left without a color and none is counted twice. A group without colors is bought without colors, so it passes.
     */
    private static function ensureTheColorsFillThePacket(mixed $colors, mixed $piecesPerPacket, Closure $fail): void
    {
        if (! is_array($colors) || $colors === []) {
            return;
        }

        $pieces = collect($colors)->sum(fn (mixed $color): float => self::number(is_array($color) ? ($color['pieces'] ?? 0) : 0));
        $packet = self::number($piecesPerPacket);

        if (abs($pieces - $packet) > 0.0001) {
            $fail(__('invoices.validation.colors_must_fill_the_packet', [
                'pieces' => Number::format($pieces, maxPrecision: 2, locale: app()->getLocale()),
                'packet' => Number::format($packet, maxPrecision: 2, locale: app()->getLocale()),
            ]));
        }
    }

    /**
     * Whether the invoice's price of one CBM is locked, because the invoice has entered a container. Never on the create page.
     */
    private static function isCbmPriceLocked(?Invoice $record): bool
    {
        return $record?->cbm_price_locked_at !== null;
    }

    /**
     * Under the initial payment, as it is typed: what the chosen supplier is owed now, what the invoice comes to,
     * what stays owed on the invoice after the initial payment, and what the supplier will be owed with this invoice.
     */
    private static function initialPaymentHint(Get $get): View
    {
        $amount = self::linesTotal($get('items'));
        $remaining = $amount - (float) str_replace(',', '', (string) $get('initial_payment'));
        $supplier = Supplier::query()->find($get('supplier_id'));

        $facts = [];

        if ($supplier !== null) {
            $debt = $supplier->totalDebt();
            $facts[] = self::fact('supplier_debt', $debt, $debt > 0 ? 'danger' : 'success', Heroicon::OutlinedBuildingStorefront);
        }

        $facts[] = self::fact('invoice_amount', $amount, 'info', Heroicon::OutlinedDocumentText);
        $facts[] = self::fact('remaining_after_payment', $remaining, match (true) {
            $remaining < 0 => 'danger',
            $remaining > 0 => 'warning',
            default => 'success',
        }, Heroicon::OutlinedReceiptPercent);

        if ($supplier !== null) {
            $debtAfter = $debt + max($remaining, 0);
            $facts[] = self::fact('supplier_debt_after', $debtAfter, $debtAfter > 0 ? 'danger' : 'success', Heroicon::OutlinedScale);
        }

        return self::facts($facts);
    }

    /**
     * The line's total price: its units (boxes × box capacity) times its unit cost, rounded to two decimals.
     */
    private static function lineTotal(mixed $boxesCount, mixed $boxQuantity, mixed $unitCost): float
    {
        return round((float) $boxesCount * (float) $boxQuantity * self::number($unitCost), 2);
    }

    /**
     * A saved line is shown by its saved unit cost, or, for a line saved without one, its total price over its units
     * (zero when it has no units).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withUnitCost(array $data): array
    {
        if (($data['unit_cost'] ?? null) !== null) {
            return $data;
        }

        $quantity = (float) ($data['quantity'] ?? 0);
        $data['unit_cost'] = $quantity > 0 ? round((float) ($data['price'] ?? 0) / $quantity, 2) : 0;

        return $data;
    }

    /**
     * A line is saved with its unit cost as typed and its total price (its units × the unit cost).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withLinePrice(array $data): array
    {
        $data['unit_cost'] = self::number($data['unit_cost'] ?? 0);
        $data['price'] = self::lineTotal($data['boxes_count'] ?? 0, $data['pieces_per_packet'] ?? 0, $data['unit_cost']);

        return $data;
    }

    /**
     * A number typed in a money field, which arrives as text carrying thousands separators.
     */
    private static function number(mixed $value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }

    /**
     * The facts as one row of small chips under a field of the line.
     *
     * @param  list<array{text: string, tooltip: string, color: string, icon: Heroicon}>  $facts
     */
    private static function facts(array $facts): View
    {
        return view('filament.forms.components.line-facts', ['facts' => $facts]);
    }

    /**
     * One fact about the item as a chip: its short text with the number written the way the panel writes numbers,
     * with up to two decimals unless more are asked for (a dash while there is none), its full explanation for the tooltip,
     * its colour, and its icon.
     *
     * @param  array<string, string>  $replace  more values for the text, beside the number
     * @return array{text: string, tooltip: string, color: string, icon: Heroicon}
     */
    private static function fact(string $name, ?float $value, string $color, Heroicon $icon, int $maxDecimals = 2, array $replace = []): array
    {
        return [
            'text' => __("filament/resources/purchase_invoice.facts.{$name}", [
                'value' => $value === null ? '—' : Number::format($value, maxPrecision: $maxDecimals, locale: app()->getLocale()),
                ...$replace,
            ]),
            'tooltip' => __("filament/resources/purchase_invoice.facts_hints.{$name}"),
            'color' => $color,
            'icon' => $icon,
        ];
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

    /**
     * The invoice's amount as the model will compute it: the sum of its lines' total prices.
     */
    private static function linesTotal(mixed $lines): float
    {
        return collect(is_array($lines) ? $lines : [])
            ->sum(fn (mixed $line): float => is_array($line)
                ? self::lineTotal($line['boxes_count'] ?? 0, $line['pieces_per_packet'] ?? 0, $line['unit_cost'] ?? 0)
                : 0.0);
    }

    /**
     * How many cartons the line takes (packets ÷ packets per carton, rounded up), shown under the packets per carton once both are typed.
     */
    private static function cartonsHint(Get $get): ?View
    {
        $cartons = self::cartonsCount($get('boxes_count'), $get('boxes_per_carton'));

        if ($cartons === null) {
            return null;
        }

        return self::facts([self::fact('cartons', $cartons, 'info', Heroicon::OutlinedArchiveBox)]);
    }

    /**
     * The cartons of the line as the model will compute them: its packets ÷ the packets one carton holds, a partly filled carton
     * counted as a whole one, or null until both are typed.
     */
    private static function cartonsCount(mixed $boxesCount, mixed $boxesPerCarton): ?float
    {
        if (blank($boxesCount) || (float) $boxesPerCarton <= 0) {
            return null;
        }

        return ceil((float) $boxesCount / (float) $boxesPerCarton);
    }

    /**
     * The space the line takes: its cartons times the CBM of one carton.
     */
    private static function lineCbm(mixed $cartonsCount, mixed $cartonCbm): float
    {
        return (float) $cartonsCount * (float) $cartonCbm;
    }

    /**
     * The invoice's CBM as the model will compute it: the sum of its lines' CBM.
     */
    private static function linesCbm(mixed $lines): float
    {
        return collect(is_array($lines) ? $lines : [])
            ->sum(fn (mixed $line): float => is_array($line)
                ? self::lineCbm(self::cartonsCount($line['boxes_count'] ?? 0, $line['boxes_per_carton'] ?? 0), $line['carton_cbm'] ?? 0)
                : 0.0);
    }

    /**
     * A CBM written the way the panel writes numbers, with up to three decimals.
     */
    private static function cbm(float $cbm): string
    {
        return Number::format($cbm, maxPrecision: self::CBM_DECIMALS, locale: app()->getLocale());
    }

    /**
     * An amount of money written the way the rest of the panel writes it, in Chinese yuan like all supplier money.
     */
    private static function money(float $amount): string
    {
        $value = FormattedValue::of($amount, Currency::ChineseYuan);

        return "{$value->number} {$value->currency}";
    }

    /**
     * The dinars in one US dollar from the dollar rate as it is typed (thousands of dinars for 100 dollars), or null while there is none.
     */
    private static function dinarsPerDollar(mixed $dinarRate): ?float
    {
        $rate = self::number($dinarRate);

        return $rate > 0 ? $rate * 1000 / 100 : null;
    }

    /**
     * An amount in yuan written in Iraqi dinars, whole: the amount ÷ the exchange rate × the dinars in one dollar,
     * or a dash while either rate is missing.
     */
    private static function dinars(float $yuan, mixed $exchangeRate, mixed $dinarRate): string
    {
        $rate = self::number($exchangeRate);
        $dinarsPerDollar = self::dinarsPerDollar($dinarRate);

        if ($rate <= 0 || $dinarsPerDollar === null) {
            return '—';
        }

        $value = FormattedValue::of($yuan / $rate * $dinarsPerDollar, Currency::IraqiDinar);

        return "{$value->number} {$value->currency}";
    }

    /**
     * An amount in yuan written in US dollars: the amount ÷ the exchange rate, or a dash while there is no rate.
     */
    private static function dollars(float $yuan, mixed $exchangeRate): string
    {
        $rate = self::number($exchangeRate);

        if ($rate <= 0) {
            return '—';
        }

        $value = FormattedValue::of($yuan / $rate, Currency::UsDollar);

        return "{$value->number} {$value->currency}";
    }
}
