<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\CustomerPaymentType;
use App\Filament\Resources\Customers\Actions\PayDebtAction;
use App\Filament\Tables\Columns\IdColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/customer.fields.name'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('filament/resources/customer.fields.phone'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('filament/resources/customer.fields.email'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('payment_type')
                    ->label(__('filament/resources/customer.fields.payment_type'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('payment_type')
                    ->label(__('filament/resources/customer.fields.payment_type'))
                    ->options(CustomerPaymentType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                PayDebtAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
