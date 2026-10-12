<?php

namespace App\Filament\Reports\Golden;

use App\Filament\Reports\Report;
use App\Metrics\Currency;
use App\Metrics\Period;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The golden report: the items that carry the period.
 *
 * It takes up to three lists of the period — the most profitable items, the most sold by quantity, and the most sold
 * by value — each of them as long as the asked count, such as the top ten. An item that is in more than one list is
 * kept once and says which lists it came from, so the report is the items themselves, never the same item twice.
 *
 * Every item is then shown from the highest profit down, with what it sold, how much of the period's sales it is,
 * what it earned, and its own profit margin, so a big seller that earns little is as visible as a small seller that
 * The table itself ends with one quiet line under the items: the profit of the whole period, how much of it these
 * items are — the answer the report is for — and the sales beside them in small letters.
 *
 * The profit is never worked out from today's cost: every sale line froze its own profit the moment it was sold,
 * so the report only adds those profits up, exactly like the profit and loss report does.
 */
class GoldenReport extends Report
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 203;

    /**
     * The lists the report can take its items from, in the order they are shown.
     */
    private const LISTS = ['profit', 'quantity', 'amount'];

    /**
     * How many items a list holds when nothing is asked for.
     */
    private const DEFAULT_COUNT = 10;

    /**
     * The largest count a list is allowed to hold.
     */
    private const MAXIMUM_COUNT = 100;

    /**
     * The sales of the whole fetched period, kept for the totals cards of the same request.
     */
    protected ?float $periodSales = null;

    /**
     * The profit of the whole fetched period, kept for the totals cards of the same request.
     */
    protected ?float $periodProfit = null;

    public static function uriKey(): string
    {
        return 'golden';
    }

    public static function label(): string
    {
        return __('filament/reports/golden.title');
    }

    /**
     * The lists the items are taken from, and how many items are taken from each one of them.
     */
    public function filterFields(): array
    {
        return [
            ToggleButtons::make('lists')
                ->label(__('filament/reports/golden.lists.label'))
                ->options([
                    'profit' => __('filament/reports/golden.lists.profit'),
                    'quantity' => __('filament/reports/golden.lists.quantity'),
                    'amount' => __('filament/reports/golden.lists.amount'),
                ])
                ->default(self::LISTS)
                ->multiple()
                ->grouped()
                ->live()
                ->required()
                ->grow(false),
            TextInput::make('count')
                ->label(__('filament/reports/golden.count.label'))
                ->numeric()
                ->default(self::DEFAULT_COUNT)
                ->minValue(1)
                ->maxValue(self::MAXIMUM_COUNT)
                ->required()
                ->live(onBlur: true)
                ->extraFieldWrapperAttributes(['style' => 'min-width: 8rem; flex-shrink: 0'])
                ->grow(false),
        ];
    }

    public function columns(): array
    {
        $percent = __('filament/reports/golden.percent');

        return [
            TextColumn::make('rank')
                ->label('#')
                ->badge()
                ->color(fn (int $state): string => $state <= 3 ? 'warning' : 'gray'),
            TextColumn::make('item')
                ->label(__('filament/reports/golden.columns.item'))
                ->weight(FontWeight::Medium),
            TextColumn::make('lists')
                ->label(__('filament/reports/golden.columns.lists'))
                ->color('gray'),
            TextColumn::make('quantity')
                ->label(__('filament/reports/golden.columns.quantity'))
                ->numeric(),
            TextColumn::make('amount')
                ->label(__('filament/reports/golden.columns.amount'))
                ->numeric(),
            TextColumn::make('sales_share')
                ->label(__('filament/reports/golden.columns.sales_share'))
                ->numeric(decimalPlaces: 1)
                ->suffix($percent)
                ->color('info'),
            TextColumn::make('profit')
                ->label(__('filament/reports/golden.columns.profit'))
                ->numeric()
                ->weight(FontWeight::Bold)
                ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),
            TextColumn::make('margin')
                ->label(__('filament/reports/golden.columns.margin'))
                ->numeric(decimalPlaces: 1)
                ->suffix($percent)
                ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),
            TextColumn::make('profit_share')
                ->label(__('filament/reports/golden.columns.profit_share'))
                ->numeric(decimalPlaces: 1)
                ->suffix($percent)
                ->weight(FontWeight::Bold)
                ->color('warning'),
        ];
    }

    /**
     * The table of the items, and the summary as its last line, inside the table under the items themselves.
     */
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->contentFooter(fn (): ?View => $this->summary() === null
                ? null
                : view('filament.reports.golden.summary', ['summary' => $this->summary()]));
    }

    public function rows(Period $period): array
    {
        $sales = $this->itemSales($period);

        $this->periodSales = (float) $period->applyTo(Order::query(), 'date')->sum('amount');
        $this->periodProfit = Order::salesProfit($period);

        $chosenLists = $this->chosenLists($sales);
        $names = Item::withTrashed()->whereIn('id', $sales->pluck('item_id'))->pluck('name', 'id');

        return $sales
            ->filter(fn (object $item): bool => isset($chosenLists[$item->item_id]))
            ->sortBy([['profit', 'desc'], ['sold_amount', 'desc'], ['item_id', 'asc']])
            ->values()
            ->map(fn (object $item, int $index): array => [
                'rank' => $index + 1,
                'item' => (string) $names[$item->item_id],
                'lists' => $this->listLabels($chosenLists[$item->item_id]),
                'quantity' => (float) $item->sold_quantity,
                'amount' => (float) $item->sold_amount,
                'sales_share' => $this->share((float) $item->sold_amount, $this->periodSales),
                'profit' => (float) $item->profit,
                'margin' => $this->share((float) $item->profit, (float) $item->sold_amount),
                'profit_share' => $this->share((float) $item->profit, $this->periodProfit),
            ])
            ->all();
    }

    /**
     * What the summary panel says: the profit and the sales of the whole period, what these items are of both of them,
     * and their own margin. Nothing before a period is fetched, or when the period sold nothing.
     *
     * @return array<string, float>|null
     */
    public function summary(): ?array
    {
        $rows = $this->fetchedRows();

        if ($rows === []) {
            return null;
        }

        $sales = (float) collect($rows)->sum('amount');
        $profit = (float) collect($rows)->sum('profit');
        $periodSales = $this->periodSales ?? 0.0;
        $periodProfit = $this->periodProfit ?? 0.0;

        return [
            'period_profit' => $periodProfit,
            'items_profit' => $profit,
            'profit_share' => $this->share($profit, $periodProfit),
            'period_sales' => $periodSales,
            'items_sales' => $sales,
            'sales_share' => $this->share($sales, $periodSales),
            'items_margin' => $this->share($profit, $sales),
        ];
    }

    /**
     * The summary line under the table is what the screen shows, so the totals are never cards here,
     * but the Excel file still ends with their numbers.
     */
    protected function hasTotalsCards(): bool
    {
        return false;
    }

    public function totals(array $rows): array
    {
        $summary = $this->summary();

        if ($summary === null) {
            return [];
        }

        return [
            ['label' => __('filament/reports/golden.summary.period_profit'), 'value' => $summary['period_profit'], 'color' => 'success', 'currency' => Currency::IraqiDinar],
            ['label' => __('filament/reports/golden.summary.items_profit'), 'value' => $summary['items_profit'], 'color' => 'success', 'currency' => Currency::IraqiDinar],
            ['label' => __('filament/reports/golden.summary.profit_share'), 'value' => $summary['profit_share'], 'color' => 'warning'],
            ['label' => __('filament/reports/golden.summary.period_sales'), 'value' => $summary['period_sales'], 'color' => 'info', 'currency' => Currency::IraqiDinar],
            ['label' => __('filament/reports/golden.summary.items_sales'), 'value' => $summary['items_sales'], 'color' => 'info', 'currency' => Currency::IraqiDinar],
        ];
    }

    /**
     * Every item sold inside the period with its sold quantity, its sales amount, and the profit its lines froze.
     * Deleted orders and deleted lines are not counted.
     *
     * @return Collection<int, object>
     */
    private function itemSales(Period $period): Collection
    {
        return OrderItem::query()
            ->whereNotNull('item_id')
            ->whereHas('order', fn (Builder $query): Builder => $period->applyTo($query, 'date'))
            ->toBase()
            ->selectRaw('item_id, sum(quantity) as sold_quantity, sum(total_price) as sold_amount, sum(profit) as profit')
            ->groupBy('item_id')
            ->get();
    }

    /**
     * The chosen items, each with the lists it came from: the head of every chosen list, as long as the asked count.
     * An item in more than one list is here once and keeps all of its lists.
     *
     * @param  Collection<int, object>  $sales
     * @return array<int, list<string>>
     */
    private function chosenLists(Collection $sales): array
    {
        $count = $this->count();
        $chosen = [];

        foreach ($this->chosenListKeys() as $list) {
            foreach ($this->headOf($sales, $list, $count) as $item) {
                $chosen[(int) $item->item_id][] = $list;
            }
        }

        return $chosen;
    }

    /**
     * The head of a list: the items with the largest profit, the largest sold quantity, or the largest sales amount,
     * as many as the asked count.
     *
     * @param  Collection<int, object>  $sales
     * @return Collection<int, object>
     */
    private function headOf(Collection $sales, string $list, int $count): Collection
    {
        $column = match ($list) {
            'profit' => 'profit',
            'quantity' => 'sold_quantity',
            default => 'sold_amount',
        };

        return $sales
            ->sortBy([[$column, 'desc'], ['item_id', 'asc']])
            ->take($count);
    }

    /**
     * The names of the lists an item came from, one after the other.
     *
     * @param  list<string>  $lists
     */
    private function listLabels(array $lists): string
    {
        return collect(self::LISTS)
            ->filter(fn (string $list): bool => in_array($list, $lists, strict: true))
            ->map(fn (string $list): string => __("filament/reports/golden.lists.{$list}"))
            ->implode(' · ');
    }

    /**
     * The chosen lists, in the report's own order. Every list when none is chosen.
     *
     * @return list<string>
     */
    private function chosenListKeys(): array
    {
        $chosen = array_values(array_intersect(self::LISTS, (array) ($this->data['lists'] ?? [])));

        return $chosen === [] ? self::LISTS : $chosen;
    }

    /**
     * How many items are taken from each list: the asked count, never less than one and never more than a hundred.
     */
    private function count(): int
    {
        $count = (int) ($this->data['count'] ?? self::DEFAULT_COUNT);

        return max(1, min(self::MAXIMUM_COUNT, $count ?: self::DEFAULT_COUNT));
    }

    /**
     * How much a part is of a whole, as a percentage. Nothing when the whole is zero.
     */
    private function share(float $part, float $whole): float
    {
        return $whole === 0.0 ? 0.0 : $part / $whole * 100;
    }
}
