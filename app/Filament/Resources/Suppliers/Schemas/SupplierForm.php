<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use App\Enums\SupplierPaymentType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_ar')
                    ->label(__('filament/resources/supplier.fields.name_ar'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_zh')
                    ->label(__('filament/resources/supplier.fields.name_zh'))
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('filament/resources/supplier.fields.phone'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('filament/resources/supplier.fields.email'))
                    ->email()
                    ->maxLength(255),
                ToggleButtons::make('payment_type')
                    ->label(__('filament/resources/supplier.fields.payment_type'))
                    ->options(SupplierPaymentType::class)
                    ->inline()
                    ->required(),
                FileUpload::make('contract')
                    ->label(__('filament/resources/supplier.fields.contract'))
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->directory('suppliers/contracts')
                    ->openable()
                    ->downloadable()
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('filament/resources/supplier.fields.notes'))
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
