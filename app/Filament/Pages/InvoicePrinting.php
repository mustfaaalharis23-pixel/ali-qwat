<?php

namespace App\Filament\Pages;

use App\Enums\OrderPaymentType;
use App\Filament\Forms\Components\DateInput;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Item;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The invoices of the sales, to look one up and print it: every order with its customer, what it came to,
 * and what the factory earned on it. The newest comes first, and the period, the customer, and the payment type narrow the list.
 * What the order cost us, its profit, and its margin show only to a user who can see what the items cost us.
 * Only a user with the invoice_printing.view permission, or a super user, can open it.
 */
class InvoicePrinting extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $routePath = 'invoice-printing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', static::class);
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.orders_and_customers');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/pages/invoice_printing.title');
    }

    public function getTitle(): string
    {
        return __('filament/pages/invoice_printing.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->with(['customer', 'cashier']))
            ->defaultSort('date', 'desc')
            ->columns([
                IdColumn::make('id')
                    ->label(__('filament/pages/invoice_printing.fields.number'))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('filament/resources/order.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('filament/resources/order.fields.customer_id'))
                    ->searchable()
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
                TextColumn::make('paid_amount')
                    ->label(__('filament/resources/order.fields.paid_amount'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_cost')
                    ->label(__('filament/resources/order.fields.total_cost'))
                    ->numeric()
                    ->sortable()
                    ->visible(fn (): bool => self::canSeeTheCost()),
                TextColumn::make('profit')
                    ->label(__('filament/resources/order.fields.profit'))
                    ->numeric()
                    ->color(fn (Order $record): string => $record->profit < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->visible(fn (): bool => self::canSeeTheCost()),
                TextColumn::make('profit_margin_percentage')
                    ->label(__('filament/resources/order.fields.profit_margin_percentage'))
                    ->state(fn (Order $record): float => $this->marginPercentageOf($record))
                    ->formatStateUsing(fn (float $state): string => $state.'%')
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw('case when total_amount > 0 then profit / total_amount else 0 end '.($direction === 'asc' ? 'asc' : 'desc')))
                    ->visible(fn (): bool => self::canSeeTheCost()),
                TextColumn::make('cashier.name')
                    ->label(__('filament/resources/order.fields.cashier_id'))
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label(__('filament/resources/order.fields.user_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('printA4')
                        ->label(__('filament/pages/invoice_printing.actions.print_a4'))
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->url(fn (Order $record): string => route('invoices.print', ['order' => $record, 'format' => 'a4']))
                        ->openUrlInNewTab(),
                    Action::make('printPos')
                        ->label(__('filament/pages/invoice_printing.actions.print_pos'))
                        ->icon(Heroicon::OutlinedReceiptPercent)
                        ->url(fn (Order $record): string => route('invoices.print', ['order' => $record, 'format' => 'pos']))
                        ->openUrlInNewTab(),
                ])
                    ->label(__('filament/pages/invoice_printing.actions.print'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->button(),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([
                        DateInput::make('from')
                            ->label(__('filament/pages/invoice_printing.filters.from')),
                        DateInput::make('until')
                            ->label(__('filament/pages/invoice_printing.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('date', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('date', '<=', $until))),
                SelectFilter::make('customer_id')
                    ->label(__('filament/resources/order.fields.customer_id'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('cashier_id')
                    ->label(__('filament/resources/order.fields.cashier_id'))
                    ->relationship('cashier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_type')
                    ->label(__('filament/resources/order.fields.payment_type'))
                    ->options(OrderPaymentType::class),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    /**
     * What the order earned out of what it was sold for, in percent, rounded to one decimal.
     * An order sold for nothing earned nothing.
     */
    private function marginPercentageOf(Order $order): float
    {
        $totalAmount = (float) $order->total_amount;

        return $totalAmount <= 0 ? 0.0 : round((float) $order->profit / $totalAmount * 100, 1);
    }

    /**
     * Whether the user is allowed to see what the items cost us, and so the profit worked out from it.
     */
    private static function canSeeTheCost(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }
}
