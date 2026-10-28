<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderPaymentType;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Item;
use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('customer.name')
                    ->label(__('filament/resources/order.fields.customer_id'))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('filament/resources/order.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_type')
                    ->label(__('filament/resources/order.fields.payment_type'))
                    ->badge(),
                TextColumn::make('items_count')
                    ->label(__('filament/resources/order.fields.items_count'))
                    ->counts('items')
                    ->numeric(),
                TextColumn::make('total_amount')
                    ->label(__('filament/resources/order.fields.total_amount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('down_payment')
                    ->label(__('filament/resources/order.fields.down_payment'))
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('weekly_payment')
                    ->label(__('filament/resources/order.fields.weekly_payment'))
                    ->numeric()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('remaining')
                    ->label(__('filament/resources/order.fields.remaining'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_cost')
                    ->label(__('filament/resources/order.fields.total_cost'))
                    ->numeric()
                    ->sortable()
                    ->visible(fn (): bool => self::canSeeTheCost()),
                TextColumn::make('profit')
                    ->label(__('filament/resources/order.fields.profit'))
                    ->numeric()
                    ->sortable()
                    ->color('success')
                    ->visible(fn (): bool => self::canSeeTheCost()),
                TextColumn::make('cashier.name')
                    ->label(__('filament/resources/order.fields.cashier_id')),
                TextColumn::make('user.name')
                    ->label(__('filament/resources/order.fields.user_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label(__('filament/resources/order.fields.customer_id'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_type')
                    ->label(__('filament/resources/order.fields.payment_type'))
                    ->options(OrderPaymentType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn (Order $record): bool => $record->trashed()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Whether the user is allowed to see what the items cost us, and so the profit worked out from it.
     */
    private static function canSeeTheCost(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }
}
