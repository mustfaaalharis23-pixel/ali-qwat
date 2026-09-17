<?php

namespace App\Filament\Resources\GeneralInvoices\Schemas;

use App\Enums\InvoiceType;
use App\Filament\Forms\Components\DateInput;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
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
                TextInput::make('name')
                    ->label(__('filament/resources/invoice.fields.name'))
                    ->required()
                    ->maxLength(255),
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
                DateInput::make('date')
                    ->label(__('filament/resources/invoice.fields.date'))
                    ->defaultToday()
                    ->required(),
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
}
