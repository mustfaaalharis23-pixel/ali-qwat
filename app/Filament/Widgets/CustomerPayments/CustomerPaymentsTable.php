<?php

namespace App\Filament\Widgets\CustomerPayments;

use App\Filament\Forms\Components\DateInput;
use App\Filament\Pages\CustomerPayments;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\CustomerPayment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

/**
 * The payments tab of the customer payments page: every payment, with its details in a modal. A payment is never edited, only deleted.
 */
class CustomerPaymentsTable extends TableWidget
{
    /**
     * The table is not added to the home dashboard on its own; the customer payments page shows it.
     */
    protected static bool $isDiscovered = false;

    public int|string|array $columnSpan = 'full';

    /**
     * Renders the table again when a payment is recorded from the page.
     */
    #[On('customer-payments-changed')]
    public function refreshPayments(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(CustomerPayment::query()->with(['customer', 'user']))
            ->heading(null)
            ->striped()
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('customer.name')
                    ->label(__('filament/resources/customer_payment.fields.customer_id'))
                    ->weight(FontWeight::SemiBold)
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (CustomerPayment $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id]))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('filament/resources/customer_payment.fields.date'))
                    ->date('Y/m/d')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('filament/resources/customer_payment.fields.amount'))
                    ->numeric()
                    ->badge()
                    ->color('success')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->sortable(),
                ImageColumn::make('image')
                    ->label(__('filament/resources/customer_payment.fields.image'))
                    ->disk('public')
                    ->url(fn (CustomerPayment $record): ?string => filled($record->image) ? Storage::disk('public')->url($record->image) : null)
                    ->openUrlInNewTab(),
                TextColumn::make('user.name')
                    ->label(__('filament/resources/customer_payment.fields.user_id')),
                TextColumn::make('notes')
                    ->label(__('filament/resources/customer_payment.fields.notes'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label(__('filament/resources/customer_payment.fields.customer_id'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('period')
                    ->schema([
                        DateInput::make('from')
                            ->label(__('filament/resources/customer_payment.filters.from')),
                        DateInput::make('until')
                            ->label(__('filament/resources/customer_payment.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', '<=', $date))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(__('filament/resources/customer_payment.sections.details'))
                    ->schema(fn (Schema $schema): Schema => CustomerPaymentInfolist::configure($schema)),
                Action::make('statement')
                    ->label(__('filament/resources/customer.actions.statement'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->url(fn (CustomerPayment $record): string => CustomerPayments::getUrl(['customer' => $record->customer_id])),
                DeleteAction::make()
                    ->after(fn () => $this->dispatch('customer-payments-changed')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn () => $this->dispatch('customer-payments-changed')),
                ]),
            ]);
    }
}
