<?php

namespace App\Filament\Resources\GeneralInvoices\Schemas;

use App\Enums\InvoiceType;
use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class GeneralInvoiceForm
{
    /**
     * Money is never withdrawn from a sales box by hand here. A notice with the only ways to withdraw shows on a money transfer invoice,
     * and as soon as a general description that holds transfer descriptions is chosen; such a general description cannot be saved.
     * The cashier shows, read only, only on an invoice that has one, because only a cashier money transfer sets it.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make(__('filament/resources/general_invoice.money_transfer_notice.heading'))
                    ->description(__('filament/resources/general_invoice.money_transfer_notice.description'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('warning')
                    ->visible(fn (?Invoice $record, Get $get): bool => $record?->isMoneyTransfer() === true
                        || GeneralDescription::find($get('general_description_id'))?->holdsTransferDescriptions() === true)
                    ->columnSpanFull(),
                Section::make(__('filament/resources/general_invoice.sections.details'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/resources/invoice.fields.name'))
                            ->required()
                            ->maxLength(255),
                        DateInput::make('date')
                            ->label(__('filament/resources/invoice.fields.date'))
                            ->defaultToday()
                            ->required(),
                        ToggleButtons::make('type')
                            ->label(__('filament/resources/invoice.fields.type'))
                            ->options(InvoiceType::class)
                            ->default(InvoiceType::Output)
                            ->inline()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('general_description_id', null))
                            ->required(),
                        Select::make('general_description_id')
                            ->label(__('filament/resources/invoice.fields.general_description_id'))
                            ->relationship(
                                'generalDescription',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('is_purchases_description', false)
                                    ->where('type', $get('type')),
                            )
                            ->disabled(fn (Get $get): bool => blank($get('type')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (GeneralDescription::find($value)?->holdsTransferDescriptions() === true) {
                                    $fail(__('filament/resources/general_invoice.money_transfer_notice.cannot_save'));
                                }
                            }),
                        Select::make('cashier_id')
                            ->label(__('filament/resources/invoice.fields.cashier_id'))
                            ->relationship(
                                'cashier',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_cashier', true),
                            )
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Invoice $record): bool => $record?->cashier_id !== null),
                    ]),
                Section::make(__('filament/resources/general_invoice.fields.items'))
                    ->icon(Heroicon::OutlinedQueueList)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make(__('filament/resources/invoice_item.fields.specific_description_id'))
                                    ->markAsRequired()
                                    ->width('70%'),
                                TableColumn::make(__('filament/resources/invoice_item.fields.price'))
                                    ->markAsRequired()
                                    ->width('30%'),
                            ])
                            ->schema([
                                Select::make('specific_description_id')
                                    ->label(__('filament/resources/invoice_item.fields.specific_description_id'))
                                    ->relationship(
                                        'specificDescription',
                                        'name',
                                        // A line only carries a description that sits under the invoice's
                                        // own general description: the model refuses anything else.
                                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                            ->where('general_description_id', $get('../../general_description_id')),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                MoneyInput::make('price')
                                    ->label(__('filament/resources/invoice_item.fields.price'))
                                    ->minValue(0)
                                    // So the amount below follows the typing instead of waiting for a save.
                                    ->live(debounce: 500)
                                    ->required(),
                            ])
                            // The invoice's amount is the sum of these prices, so an invoice with no
                            // line carries no money at all. The general description comes first,
                            // because it decides which descriptions a line may choose from.
                            ->minItems(1)
                            ->defaultItems(1)
                            ->disabled(fn (Get $get): bool => blank($get('general_description_id'))),
                    ]),
                // Read only on purpose: the model owns the amount, it is the sum of the lines
                // above, and a general invoice is always fully paid — it carries no debt.
                Section::make(__('filament/resources/general_invoice.sections.amounts'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->schema([
                        TextEntry::make('amount_preview')
                            ->label(__('filament/resources/invoice.fields.amount'))
                            ->state(fn (Get $get): string => self::money(self::linesTotal($get('items'))))
                            ->badge()
                            ->color('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold),
                    ])
                    ->columnSpanFull(),
                Section::make(__('filament/resources/general_invoice.sections.attachments'))
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        // Optional here, unlike a purchase invoice: a general invoice is often written
                        // from a receipt that has no paper to photograph. The column keeps an empty list.
                        FileUpload::make('images')
                            ->label(__('filament/resources/invoice.fields.images'))
                            ->multiple()
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('invoices'),
                        Textarea::make('notes')
                            ->label(__('filament/resources/invoice.fields.notes'))
                            ->rows(4),
                    ]),
            ]);
    }

    /**
     * The invoice's amount as the model will compute it: the sum of its lines' prices.
     * A price arrives from the field as text carrying thousands separators, so those go first.
     */
    private static function linesTotal(mixed $lines): float
    {
        return collect(is_array($lines) ? $lines : [])
            ->sum(fn (mixed $line): float => is_array($line)
                ? (float) str_replace(',', '', (string) ($line['price'] ?? 0))
                : 0.0);
    }

    /**
     * An amount of money written the way the rest of the panel writes it.
     */
    private static function money(float $amount): string
    {
        $value = FormattedValue::of($amount, Currency::IraqiDinar);

        return "{$value->number} {$value->currency}";
    }
}
