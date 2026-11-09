<?php

namespace App\Reports;

use App\Metrics\Currency;
use App\Metrics\Period;
use App\Models\Item;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * The item sales report: the items sold inside the period, each with its rank, its sold quantity, and its sales amount,
 * sorted by the chosen sort ($data['sort']): the most sold quantity or the most sold value. Under the table is the total
 * sales amount of the items. The amount is the sum of the order lines' total prices. Deleted orders and deleted lines are not counted.
 */
class ItemSalesReport extends Report
{
    public static function uriKey(): string
    {
        return 'item-sales';
    }

    public static function label(): string
    {
        return __('filament/reports/item_sales.title');
    }

    public function columns(): array
    {
        return [
            $this->column('rank', '#'),
            $this->column('item', __('filament/reports/item_sales.columns.item')),
            $this->column('quantity', __('filament/reports/item_sales.columns.quantity')),
            $this->column('amount', __('filament/reports/item_sales.columns.amount')),
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
