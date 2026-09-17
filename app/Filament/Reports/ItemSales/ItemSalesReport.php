<?php

namespace App\Filament\Reports\ItemSales;

use App\Filament\Reports\Report;
use App\Metrics\Currency;
use App\Metrics\Period;
use App\Models\Item;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * The item sales report: the items sold inside the period, each with its rank, its sold quantity, and its sales amount,
 * sorted by the chosen sort: the most sold quantity or the most sold value. Under the table is the total sales amount of the items.
 * The amount is the sum of the order lines' total prices. Deleted orders and deleted lines are not counted.
 */
class ItemSalesReport extends Report
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 202;

    public static function uriKey(): string
    {
        return 'item-sales';
    }

    public static function label(): string
    {
        return __('filament/reports/item_sales.title');
    }

    /**
     * The sort choice. It re-sorts the fetched rows right away, without fetching again.
     */
    public function filterFields(): array
    {
        return [
            ToggleButtons::make('sort')
                ->label(__('filament/reports/item_sales.sort.label'))
                ->options([
                    'quantity' => __('filament/reports/item_sales.sort.quantity'),
                    'amount' => __('filament/reports/item_sales.sort.amount'),
                ])
                ->default('quantity')
                ->grouped()
                ->live()
                ->grow(false),
        ];
    }

    public function columns(): array
    {
        return [
            TextColumn::make('rank')
                ->label('#')
                ->badge()
                ->color(fn (int $state): string => $state <= 3 ? 'primary' : 'gray'),
            TextColumn::make('item')
                ->label(__('filament/reports/item_sales.columns.item'))
                ->weight(FontWeight::Medium),
            TextColumn::make('quantity')
                ->label(__('filament/reports/item_sales.columns.quantity'))
                ->numeric()
                ->color(fn (): ?string => $this->sortBy() === 'quantity' ? 'success' : null)
                ->weight(fn (): ?FontWeight => $this->sortBy() === 'quantity' ? FontWeight::Bold : null),
            TextColumn::make('amount')
                ->label(__('filament/reports/item_sales.columns.amount'))
                ->numeric()
                ->color(fn (): ?string => $this->sortBy() === 'amount' ? 'success' : null)
                ->weight(fn (): ?FontWeight => $this->sortBy() === 'amount' ? FontWeight::Bold : null),
        ];
    }

    public function rows(Period $period): array
    {
        [$first, $second] = $this->sortBy() === 'amount'
            ? ['sold_amount', 'sold_quantity']
            : ['sold_quantity', 'sold_amount'];

        $sales = OrderItem::query()
            ->whereNotNull('item_id')
            ->whereHas('order', fn (Builder $query): Builder => $period->applyTo($query, 'date'))
            ->toBase()
            ->selectRaw('item_id, sum(quantity) as sold_quantity, sum(total_price) as sold_amount')
            ->groupBy('item_id')
            ->orderByDesc($first)
            ->orderByDesc($second)
            ->orderBy('item_id')
            ->get();

        $names = Item::withTrashed()->whereIn('id', $sales->pluck('item_id'))->pluck('name', 'id');

        return $sales
            ->values()
            ->map(fn (object $sale, int $index): array => [
                'rank' => $index + 1,
                'item' => $names[$sale->item_id],
                'quantity' => (float) $sale->sold_quantity,
                'amount' => (float) $sale->sold_amount,
            ])
            ->all();
    }

    public function totals(array $rows): array
    {
        return [
            [
                'label' => __('filament/reports/item_sales.totals.amount'),
                'value' => (float) collect($rows)->sum('amount'),
                'color' => 'primary',
                'currency' => Currency::IraqiDinar,
            ],
        ];
    }

    /**
     * The chosen sort: amount for the most sold value, otherwise quantity for the most sold quantity.
     */
    private function sortBy(): string
    {
        return ($this->data['sort'] ?? null) === 'amount' ? 'amount' : 'quantity';
    }
}
