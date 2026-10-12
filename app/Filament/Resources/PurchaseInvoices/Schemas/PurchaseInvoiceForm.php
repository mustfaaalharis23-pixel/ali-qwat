<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Enums\ExchangeRateSource;
use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\InvoiceCategories\InvoiceCategoryResource;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SpecificDescription;
use App\Models\Supplier;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
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
     * Each line is an item with its packets (boxes_count), packet capacity (box_quantity), packets per carton, carton volume (CBM), and unit cost;
     * the quantity (packets × capacity), the cartons (packets ÷ packets per carton, rounded up) and the CBM (cartons × carton volume) are calculated by the model, and the line is saved with its unit cost as typed and its total price (its units × the unit cost).
     * The invoice's price of one CBM, in US dollars, gives the transport cost of every line, and its dollar rate in dinars
     * turns the dollar costs into dinars, up to the final cost of one piece.
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
                    // A card per line: the item and its packets on the first row, the cartons and the unit cost on the second.
                    ->columns(3)
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
                            ->helperText(fn (mixed $state): ?View => self::itemHint($state)),
                        // Live, so the line total under the unit cost follows the typing.
                        TextInput::make('boxes_count')
                            ->label(__('filament/resources/invoice_item.fields.boxes_count'))
                            ->numeric()
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->required(),
                        TextInput::make('box_quantity')
                            ->label(__('filament/resources/invoice_item.fields.box_quantity'))
                            ->numeric()
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->required()
                            ->helperText(fn (Get $get): ?View => self::quantityHint($get)),
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
                        MoneyInput::make('unit_cost')
                            ->label(__('filament/resources/purchase_invoice.fields.unit_cost'))
                            ->suffix(Currency::ChineseYuan->symbol())
                            ->minValue(0)
                            // So the amounts below follow the typing instead of waiting for a save.
                            ->live(debounce: 500)
                            ->required()
                            ->helperText(fn (Get $get): ?View => self::costHint($get)),
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
     * Under the unit cost: the line's total price as it is typed (boxes × box capacity × unit cost), a dash until
     * the line has a quantity and a unit cost, the unit cost and the line total in US dollars once the invoice has an exchange rate,
     * the same in dinars and the final cost of one piece (its cost + its transport) once it also has a dollar rate in dinars,
     * and what one unit of the item costs now.
     * The item's cost shows only to a user who can see the cost: red when this line's final cost is dearer, green when it is cheaper.
     */
    private static function costHint(Get $get): View
    {
        $total = self::lineTotal($get('boxes_count'), $get('box_quantity'), $get('unit_cost'));
        $facts = [self::fact('line_total', $total > 0 ? $total : null, 'primary', Heroicon::OutlinedCalculator)];

        $rate = self::number($get('../../exchange_rate'));
        $dinarsPerDollar = self::dinarsPerDollar($get('../../dinar_rate'));
        $finalUnitCost = null;

        if ($rate > 0) {
            $unitCost = self::number($get('unit_cost'));
            $facts[] = self::fact('unit_cost_usd', $unitCost > 0 ? $unitCost / $rate : null, 'success', Heroicon::OutlinedCurrencyDollar);
            $facts[] = self::fact('line_total_usd', $total > 0 ? $total / $rate : null, 'success', Heroicon::OutlinedCurrencyDollar);

            if ($dinarsPerDollar !== null) {
                $quantity = (float) $get('boxes_count') * (float) $get('box_quantity');
                $cbm = self::lineCbm(self::cartonsCount($get('boxes_count'), $get('boxes_per_carton')), $get('carton_cbm'));
                $transportPerPiece = $quantity > 0 ? $cbm * self::number($get('../../cbm_price')) / $quantity : 0.0;
                $finalUnitCost = $unitCost > 0 ? ($unitCost / $rate + $transportPerPiece) * $dinarsPerDollar : null;

                // A dinar has no smaller part worth showing, so the dinar amounts are whole.
                $facts[] = self::fact('unit_cost_iqd', $unitCost > 0 ? $unitCost / $rate * $dinarsPerDollar : null, 'warning', Heroicon::OutlinedBanknotes, 0);
                $facts[] = self::fact('line_total_iqd', $total > 0 ? $total / $rate * $dinarsPerDollar : null, 'warning', Heroicon::OutlinedBanknotes, 0);
                $facts[] = self::fact('final_unit_cost_iqd', $finalUnitCost, 'primary', Heroicon::OutlinedCheckBadge, 0);
            }
        }

        $item = self::itemOf($get('specific_description_id'));

        if ($item !== null && (auth()->user()?->can('viewCostPrice', Item::class) ?? false)) {
            $previous = (float) $item->cost_price;
            $current = $finalUnitCost ?? 0.0;

            $facts[] = self::fact('previous_unit_cost', $previous, ...match (true) {
                $previous > 0 && $current > $previous + 0.0001 => ['danger', Heroicon::OutlinedArrowTrendingUp],
                $previous > 0 && $current > 0 && $current < $previous - 0.0001 => ['success', Heroicon::OutlinedArrowTrendingDown],
                default => ['gray', Heroicon::OutlinedClock],
            });
        }

        return self::facts($facts);
    }

    /**
     * How many units the line brings in (boxes × box quantity), shown under the box quantity once both are typed.
     */
    private static function quantityHint(Get $get): ?View
    {
        $quantity = (float) $get('boxes_count') * (float) $get('box_quantity');

        if ($quantity <= 0) {
            return null;
        }

        return self::facts([self::fact('quantity', $quantity, 'info', Heroicon::OutlinedSquares2x2)]);
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
            $quantity = (float) $get('boxes_count') * (float) $get('box_quantity');

            $facts[] = self::fact('transport_cost', $transportCost, 'info', Heroicon::OutlinedTruck);
            // One piece can cost a fraction of a cent to ship, so it keeps more decimals.
            $facts[] = self::fact('transport_cost_per_piece', $quantity > 0 ? $transportCost / $quantity : null, 'info', Heroicon::OutlinedCurrencyDollar, 4);
        }

        return self::facts($facts);
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
        $data['price'] = self::lineTotal($data['boxes_count'] ?? 0, $data['box_quantity'] ?? 0, $data['unit_cost']);

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
     * @return array{text: string, tooltip: string, color: string, icon: Heroicon}
     */
    private static function fact(string $name, ?float $value, string $color, Heroicon $icon, int $maxDecimals = 2): array
    {
        return [
            'text' => __("filament/resources/purchase_invoice.facts.{$name}", [
                'value' => $value === null ? '—' : Number::format($value, maxPrecision: $maxDecimals, locale: app()->getLocale()),
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
                ? self::lineTotal($line['boxes_count'] ?? 0, $line['box_quantity'] ?? 0, $line['unit_cost'] ?? 0)
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
