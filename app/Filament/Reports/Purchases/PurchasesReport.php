<?php

namespace App\Filament\Reports\Purchases;

use App\Filament\Reports\Report;
use App\Metrics\Currency;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * The purchases report: one row per supplier with its total purchases inside the period, from the largest to the smallest,
 * and under the table the total of all purchases. Each row opens the items bought from the supplier with their quantities and amounts.
 * The purchases are the purchase invoices' amounts, pending or confirmed. Deleted invoices and deleted lines are not counted.
 */
class PurchasesReport extends Report
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 201;

    public static function uriKey(): string
    {
        return 'purchases';
    }

    public static function label(): string
    {
        return __('filament/reports/purchases.title');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('supplier')
                ->label(__('filament/reports/purchases.columns.supplier')),
            TextColumn::make('amount')
                ->label(__('filament/reports/purchases.columns.amount'))
                ->numeric()
                ->weight(FontWeight::Bold),
        ];
    }

    public function rows(Period $period): array
    {
        $purchases = $period->applyTo(Invoice::query()->purchases(), 'date')
            ->whereNotNull('supplier_id')
            ->toBase()
            ->selectRaw('supplier_id, sum(amount) as total')
            ->groupBy('supplier_id')
            ->havingRaw('sum(amount) > 0')
            ->orderByDesc('total')
            ->orderBy('supplier_id')
            ->get();

        $names = Supplier::withTrashed()->whereIn('id', $purchases->pluck('supplier_id'))->pluck('name_ar', 'id');

        return $purchases
            ->map(fn (object $purchase): array => [
                'supplier_id' => (int) $purchase->supplier_id,
                'supplier' => $names[$purchase->supplier_id],
                'amount' => (float) $purchase->total,
            ])
            ->all();
    }

    public function totals(array $rows): array
    {
        return [
            [
                'label' => __('filament/reports/purchases.totals.amount'),
                'value' => (float) collect($rows)->sum('amount'),
                'color' => 'primary',
                'currency' => Currency::IraqiDinar,
            ],
        ];
    }

    /**
     * Opens the items bought from the supplier of the row inside the fetched period.
     */
    public function recordActions(): array
    {
        return [
            Action::make('items')
                ->label(__('filament/reports/purchases.items.action'))
                ->icon(Heroicon::OutlinedCube)
                ->color('gray')
                ->modalHeading(fn (array $record): string => __('filament/reports/purchases.items.heading', ['supplier' => $record['supplier']]))
                ->modalContent(fn (array $record): View => view('filament.reports.purchases.items', [
                    'items' => $this->itemsBoughtFrom($record['supplier_id']),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament/reports/purchases.items.close')),
        ];
    }

    /**
     * The items bought from the supplier inside the fetched period, from the largest amount to the smallest:
     * each item's name, its bought quantity, and the sum of its line prices.
     *
     * @return list<array{name: string, quantity: float, amount: float}>
     */
    private function itemsBoughtFrom(int $supplierId): array
    {
        $period = $this->fetchedPeriod();

        $lines = InvoiceItem::query()
            ->whereNotNull('item_id')
            ->whereHas('invoice', fn (Builder $query): Builder => $period->applyTo($query->purchases()->where('supplier_id', $supplierId), 'date'))
            ->toBase()
            ->selectRaw('item_id, sum(quantity) as bought_quantity, sum(price) as bought_amount')
            ->groupBy('item_id')
            ->orderByDesc('bought_amount')
            ->orderBy('item_id')
            ->get();

        $names = Item::withTrashed()->whereIn('id', $lines->pluck('item_id'))->pluck('name', 'id');

        return $lines
            ->map(fn (object $line): array => [
                'name' => $names[$line->item_id],
                'quantity' => (float) $line->bought_quantity,
                'amount' => (float) $line->bought_amount,
            ])
            ->all();
    }
}
