<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerPaymentType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/resources/customer.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('filament/resources/customer.fields.phone'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('filament/resources/customer.fields.email'))
                    ->email()
                    ->maxLength(255),
                ToggleButtons::make('payment_type')
                    ->label(__('filament/resources/customer.fields.payment_type'))
                    ->options(CustomerPaymentType::class)
                    ->inline()
                    ->required(),
                FileUpload::make('contract')
                    ->label(__('filament/resources/customer.fields.contract'))
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->directory('customers/contracts')
                    ->openable()
                    ->downloadable()
                    ->columnSpanFull(),
            ]);
    }
}
