<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Tables\Columns\IdColumn;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

/**
 * The customer's payments. They are recorded with the pay debt action, never edited here, and only deleted:
 * deleting a payment gives its orders their remaining back and deletes its withdrawal invoice.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/customer_payment.plural_label');
    }

    /**
     * Payments are deleted from the customer's details page too, so the table is not read only there.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Renders the table again when a payment is recorded from the page.
     */
    #[On('customer-payments-changed')]
    public function refreshPayments(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->modelLabel(__('filament/resources/customer_payment.label'))
            ->pluralModelLabel(__('filament/resources/customer_payment.plural_label'))
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('date')
                    ->label(__('filament/resources/customer_payment.fields.date'))
                    ->date('Y/m/d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('filament/resources/customer_payment.fields.amount'))
                    ->numeric()
                    ->sortable(),
                ImageColumn::make('image')
                    ->label(__('filament/resources/customer_payment.fields.image'))
                    ->disk('public')
                    ->url(fn (Model $record): ?string => filled($record->image) ? Storage::disk('public')->url($record->image) : null)
                    ->openUrlInNewTab(),
                TextColumn::make('user.name')
                    ->label(__('filament/resources/customer_payment.fields.user_id')),
                TextColumn::make('notes')
                    ->label(__('filament/resources/customer_payment.fields.notes'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->after(fn () => $this->dispatch('customer-payments-changed')),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
