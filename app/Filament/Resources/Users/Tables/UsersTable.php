<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Tables\Columns\IdColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/user.fields.name')),
                TextColumn::make('email')
                    ->label(__('filament/resources/user.fields.email')),
                IconColumn::make('is_super')
                    ->label(__('filament/resources/user.fields.is_super'))
                    ->boolean()
                    ->superOnly(),
                IconColumn::make('is_cashier')
                    ->label(__('filament/resources/user.fields.is_cashier'))
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
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
