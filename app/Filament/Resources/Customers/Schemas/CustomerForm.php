<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerPaymentType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/customer.sections.details'))
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/resources/customer.fields.name'))
                            ->required()
                            ->maxLength(255),
                        ToggleButtons::make('payment_type')
                            ->label(__('filament/resources/customer.fields.payment_type'))
                            ->options(CustomerPaymentType::class)
                            ->inline()
                            ->required(),
                        TextInput::make('phone')
                            ->label(__('filament/resources/customer.fields.phone'))
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('filament/resources/customer.fields.email'))
                            ->email()
                            ->maxLength(255),
                        Toggle::make('is_invoice_by_invoice')
                            ->label(__('filament/resources/customer.fields.is_invoice_by_invoice'))
                            ->helperText(__('filament/resources/customer.fields.is_invoice_by_invoice_hint'))
                            ->default(false)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament/resources/customer.fields.contract'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('contract')
                            ->hiddenLabel()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->disk('public')
                            ->directory('customers/contracts')
                            ->openable()
                            ->downloadable(),
                    ]),
            ]);
    }
}
