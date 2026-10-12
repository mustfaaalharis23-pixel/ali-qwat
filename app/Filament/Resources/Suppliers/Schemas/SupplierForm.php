<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/supplier.sections.details'))
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
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
                    ]),
                Section::make(__('filament/resources/supplier.sections.documents'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('contract')
                            ->label(__('filament/resources/supplier.fields.contract'))
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240)
                            ->disk('public')
                            ->directory('suppliers/contracts')
                            ->openable()
                            ->downloadable(),
                        Textarea::make('notes')
                            ->label(__('filament/resources/supplier.fields.notes'))
                            ->rows(4),
                    ]),
            ]);
    }
}
