<?php

namespace App\Filament\Resources\PurchaseInvoices\RelationManagers;

use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Tables\Columns\IdColumn;
use App\Metrics\Currency;
use App\Models\Invoice;
use App\Models\Payoff;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The payoffs of a purchase invoice, shown at the bottom of its page.
 * After every change the page is told to reload the invoice, so its paid amount and remaining are up to date.
 */
class PayoffsRelationManager extends RelationManager
{
    protected static string $relationship = 'payoffs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/payoff.plural_label');
    }

    /**
     * Payoffs are managed from the invoice's details page too, so the table is not read only there.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MoneyInput::make('amount')
                    ->label(__('filament/resources/payoff.fields.amount'))
                    ->suffix(Currency::ChineseYuan->symbol())
                    ->minValue(0.01)
                    ->maxValue(fn (?Payoff $record): float => $this->maximumAmount($record))
                    ->required(),
                // The calendar is in English, so the date's digits stay English in Arabic too, like the invoice's date.
                DateInput::make('date')
                    ->label(__('filament/resources/payoff.fields.date'))
                    ->locale('en')
                    ->defaultToday()
                    ->required(),
                FileUpload::make('image')
                    ->label(__('filament/resources/payoff.fields.image'))
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('payoffs')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->modelLabel(__('filament/resources/payoff.label'))
            ->pluralModelLabel(__('filament/resources/payoff.plural_label'))
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('date')
                    ->label(__('filament/resources/payoff.fields.date'))
                    ->date('Y/m/d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('filament/resources/payoff.fields.amount'))
                    ->numeric()
                    ->description(fn (Payoff $record): ?string => $record->is_initial_payment
                        ? __('filament/resources/payoff.fields.is_initial_payment')
                        : null)
                    ->sortable(),
                ImageColumn::make('image')
                    ->label(__('filament/resources/payoff.fields.image'))
                    ->disk('public'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn () => $this->dispatch('payoffs-changed')),
            ])
            // The initial payment is never changed or deleted on its own, so its row has none of these.
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (Payoff $record): bool => $record->is_initial_payment)
                    ->after(fn () => $this->dispatch('payoffs-changed')),
                DeleteAction::make()
                    ->hidden(fn (Payoff $record): bool => $record->is_initial_payment)
                    ->after(fn () => $this->dispatch('payoffs-changed')),
                ForceDeleteAction::make()
                    ->hidden(fn (Payoff $record): bool => $record->is_initial_payment)
                    ->after(fn () => $this->dispatch('payoffs-changed')),
                RestoreAction::make()
                    ->after(fn () => $this->dispatch('payoffs-changed')),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Payoff $record): bool => ! $record->is_initial_payment)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn () => $this->dispatch('payoffs-changed')),
                    ForceDeleteBulkAction::make()
                        ->after(fn () => $this->dispatch('payoffs-changed')),
                    RestoreBulkAction::make()
                        ->after(fn () => $this->dispatch('payoffs-changed')),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }

    /**
     * The largest amount the payoff can have: what remains on the invoice, plus this payoff's own amount when it is edited.
     */
    protected function maximumAmount(?Payoff $payoff): float
    {
        /** @var Invoice $invoice */
        $invoice = $this->getOwnerRecord()->fresh();

        return round($invoice->remaining + ($payoff?->amount ?? 0), 2);
    }
}
