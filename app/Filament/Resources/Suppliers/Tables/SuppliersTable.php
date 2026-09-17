<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Enums\SupplierPaymentType;
use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Tables\Columns\IdColumn;
use App\Invoices\DistributeSupplierPayoff;
use App\Models\Payoff;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name_ar')
                    ->label(__('filament/resources/supplier.fields.name_ar'))
                    ->searchable(),
                TextColumn::make('name_zh')
                    ->label(__('filament/resources/supplier.fields.name_zh'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('filament/resources/supplier.fields.phone')),
                TextColumn::make('email')
                    ->label(__('filament/resources/supplier.fields.email'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('payment_type')
                    ->label(__('filament/resources/supplier.fields.payment_type'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('payment_type')
                    ->label(__('filament/resources/supplier.fields.payment_type'))
                    ->options(SupplierPaymentType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                self::distributePayoffAction(),
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

    /**
     * Pays one amount on the supplier's purchase invoices, from the oldest to the newest.
     * It shows only for suppliers that are not deleted and to users who can create payoffs.
     */
    private static function distributePayoffAction(): Action
    {
        return Action::make('distributePayoff')
            ->label(__('filament/resources/supplier.actions.distribute_payoff.label'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (Supplier $record): bool => ! $record->trashed() && (auth()->user()?->can('create', Payoff::class) ?? false))
            ->modalHeading(fn (Supplier $record): string => __('filament/resources/supplier.actions.distribute_payoff.heading', ['supplier' => $record->name_ar]))
            ->modalDescription(fn (Supplier $record): string => __('filament/resources/supplier.actions.distribute_payoff.debt', [
                'debt' => Number::format((new DistributeSupplierPayoff)->debtOf($record), precision: 2),
            ]))
            ->schema([
                MoneyInput::make('amount')
                    ->label(__('filament/resources/supplier.actions.distribute_payoff.amount'))
                    ->minValue(0.01)
                    ->maxValue(fn (Supplier $record): float => (new DistributeSupplierPayoff)->debtOf($record))
                    ->required(),
                DateInput::make('date')
                    ->label(__('filament/resources/supplier.actions.distribute_payoff.date'))
                    ->defaultToday()
                    ->required(),
            ])
            ->action(function (array $data, Supplier $record): void {
                (new DistributeSupplierPayoff)->handle(auth()->user(), $record, (float) $data['amount'], $data['date']);

                Notification::make()
                    ->title(__('filament/resources/supplier.actions.distribute_payoff.done'))
                    ->success()
                    ->send();
            });
    }
}
