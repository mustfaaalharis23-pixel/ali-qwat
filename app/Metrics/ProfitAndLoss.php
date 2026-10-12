<?php

namespace App\Metrics;

use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\SpecificDescription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The profit and loss of a period, and the proof that it is right.
 *
 * The profit is not worked out from the sales minus the cost of what was sold: every sale line froze its own profit
 * the moment it was sold, so the report only adds those profits up. Buying goods is not an expense either — it turns
 * money into stock, and it turns into a profit when the goods are sold — so the purchase invoices are on neither side.
 *
 *   revenue    = the profit of the sales + the external revenues
 *   expenses   = the operating expenses
 *   net profit = revenue − expenses
 *
 * The proof: what the company is worth can only change by what it earned and by the capital the partners moved.
 *
 *   computed value = the value of the company at the start of the period + net profit + capital movement
 *   difference     = the real value at the end of the period − the computed value
 *
 * So a difference that is not zero means something in the period does not explain itself, and that is the point of
 * the report: it is its own alarm. The one thing it cannot explain yet is a change in the cost of goods that were
 * already in the stock, because that difference is not written down anywhere in the system.
 *
 * A description the reader left out is still in the statement, marked as left out, and only the totals skip it.
 * While anything is left out, the statement says nothing about matching: see App\Metrics\Exclusions.
 */
readonly class ProfitAndLoss
{
    /**
     * How far the difference is allowed to be from zero and still count as matching: less than one dinar,
     * so the rounding of the costs never shows up as a mismatch.
     */
    public const MATCHING_TOLERANCE = 1.0;

    /**
     * @param  list<array{id: int, name: string, amount: float, isExcluded: bool}>  $externalRevenues
     * @param  list<array{id: int, name: string, amount: float, isExcluded: bool}>  $expenseGroups
     * @param  list<array{general_description_id: int, name: string, amount: float, invoice_id: int, invoice_name: string, invoice_date: string}>  $expenseLines
     */
    public function __construct(
        public Period $period,
        public float $salesProfit,
        public float $salesAmount,
        public float $purchasesAmount,
        public array $externalRevenues,
        public array $expenseGroups,
        public array $expenseLines,
        public float $capitalMovement,
        public CompanyValue $valueAtStart,
        public CompanyValue $valueAtEnd,
        public Exclusions $exclusions,
    ) {}

    /**
     * The profit and loss of a closed period: a period that has a first and a last moment, such as a report's period.
     * The value at the start of the period is the value of the company one second before it begins, which is the end
     * of the day before it.
     */
    public static function for(Period $period, ?Exclusions $exclusions = null): self
    {
        $exclusions ??= new Exclusions;
        $expenseLines = self::expenseLines($period);

        return new self(
            period: $period,
            salesProfit: Order::salesProfit($period),
            salesAmount: Order::salesAmount($period),
            purchasesAmount: Invoice::purchasesAmount($period),
            externalRevenues: self::externalRevenues($period, $exclusions),
            expenseGroups: self::expenseGroups($expenseLines, $exclusions),
            expenseLines: $expenseLines,
            capitalMovement: Invoice::capitalMovement($period),
            valueAtStart: CompanyValue::at($period->start->subSecond()),
            valueAtEnd: CompanyValue::at($period->end),
            exclusions: $exclusions,
        );
    }

    /**
     * The revenue of the period: the profit of the sales and the external revenues together,
     * without the revenues that are left out.
     */
    public function totalRevenue(): float
    {
        return $this->salesProfit + $this->sumOfWhatIsCounted($this->externalRevenues);
    }

    /**
     * The margin the period sold at: how much of every hundred dinars of sales stayed as a profit of the sales.
     * The expenses are not in it, because it says what the selling itself earns, not what the company earned.
     */
    public function salesMargin(): float
    {
        return $this->salesAmount === 0.0 ? 0.0 : $this->salesProfit / $this->salesAmount * 100;
    }

    /**
     * The expenses of the period, without the expenses that are left out.
     */
    public function totalExpenses(): float
    {
        return $this->sumOfWhatIsCounted($this->expenseGroups);
    }

    /**
     * What the period earned: its revenue minus its expenses.
     */
    public function netProfit(): float
    {
        return $this->totalRevenue() - $this->totalExpenses();
    }

    /**
     * What the company should be worth at the end of the period: what it was worth at the start,
     * plus what the period earned, plus the capital the partners moved inside it.
     */
    public function computedValue(): float
    {
        return $this->valueAtStart->total() + $this->netProfit() + $this->capitalMovement;
    }

    /**
     * What the real value is above the computed value. Zero means the report explains every dinar.
     */
    public function difference(): float
    {
        return $this->valueAtEnd->total() - $this->computedValue();
    }

    /**
     * Whether the report matches: the difference is smaller than one dinar.
     * Only ask it when the statement checks itself.
     */
    public function isMatched(): bool
    {
        return abs($this->difference()) < self::MATCHING_TOLERANCE;
    }

    /**
     * Whether the statement checks itself: it does not while a description is left out, because the net profit
     * is then the profit of a part of the month, and the value of the company is of the whole of it.
     */
    public function checksItself(): bool
    {
        return ! $this->exclusions->any();
    }

    /**
     * The amounts of the rows that are counted, which is all of them except the ones that are left out.
     *
     * @param  list<array{amount: float, isExcluded: bool}>  $rows
     */
    private function sumOfWhatIsCounted(array $rows): float
    {
        return (float) collect($rows)
            ->reject(fn (array $row): bool => $row['isExcluded'])
            ->sum('amount');
    }

    /**
     * The expense lines under one general description, from the largest amount to the smallest.
     *
     * @return list<array{general_description_id: int, name: string, amount: float, invoice_id: int, invoice_name: string, invoice_date: string}>
     */
    public function linesOf(int $generalDescriptionId): array
    {
        return array_values(array_filter(
            $this->expenseLines,
            fn (array $line): bool => $line['general_description_id'] === $generalDescriptionId,
        ));
    }

    /**
     * The external revenues of the period, from the largest to the smallest, each under the name of its specific
     * description, even when that description is deleted. The lines are what is added up, not the invoice amounts,
     * because the amount of a line is what was earned under its own description.
     *
     * @return list<array{id: int, name: string, amount: float, isExcluded: bool}>
     */
    private static function externalRevenues(Period $period, Exclusions $exclusions): array
    {
        $lines = InvoiceItem::query()
            ->whereHas('invoice', fn (Builder $query): Builder => $period->applyTo($query->externalRevenues(), 'date'))
            ->toBase()
            ->selectRaw('specific_description_id, sum(price) as amount')
            ->groupBy('specific_description_id')
            ->orderByDesc('amount')
            ->orderBy('specific_description_id')
            ->get();

        $names = SpecificDescription::withTrashed()
            ->whereIn('id', $lines->pluck('specific_description_id'))
            ->pluck('name', 'id');

        return $lines
            ->map(fn (object $line): array => [
                'id' => (int) $line->specific_description_id,
                'name' => (string) $names[$line->specific_description_id],
                'amount' => (float) $line->amount,
                'isExcluded' => $exclusions->excludesRevenue((int) $line->specific_description_id),
            ])
            ->all();
    }

    /**
     * Every operating expense line of the period, with the invoice it is on and the general description it is under,
     * from the largest amount to the smallest inside each general description. A deleted description keeps its name.
     *
     * @return list<array{general_description_id: int, name: string, amount: float, invoice_id: int, invoice_name: string, invoice_date: string}>
     */
    private static function expenseLines(Period $period): array
    {
        $lines = InvoiceItem::query()
            ->whereHas('invoice', fn (Builder $query): Builder => $period->applyTo($query->operatingExpenses(), 'date'))
            ->toBase()
            ->select(['invoice_id', 'specific_description_id', 'price'])
            ->orderByDesc('price')
            ->get();

        $descriptions = SpecificDescription::withTrashed()
            ->whereIn('id', $lines->pluck('specific_description_id'))
            ->get(['id', 'name', 'general_description_id'])
            ->keyBy('id');

        $invoices = Invoice::withTrashed()
            ->whereIn('id', $lines->pluck('invoice_id'))
            ->get(['id', 'name', 'date'])
            ->keyBy('id');

        return $lines
            ->map(fn (object $line): array => [
                'general_description_id' => (int) $descriptions[$line->specific_description_id]->general_description_id,
                'name' => (string) $descriptions[$line->specific_description_id]->name,
                'amount' => (float) $line->price,
                'invoice_id' => (int) $line->invoice_id,
                'invoice_name' => (string) $invoices[$line->invoice_id]->name,
                'invoice_date' => $invoices[$line->invoice_id]->date->toDateString(),
            ])
            ->all();
    }

    /**
     * The expenses of the period added up under every general description, from the largest to the smallest.
     *
     * @param  list<array{general_description_id: int, name: string, amount: float, invoice_id: int, invoice_name: string, invoice_date: string}>  $expenseLines
     * @return list<array{id: int, name: string, amount: float, isExcluded: bool}>
     */
    private static function expenseGroups(array $expenseLines, Exclusions $exclusions): array
    {
        $groups = collect($expenseLines)->groupBy('general_description_id');

        $names = GeneralDescription::withTrashed()
            ->whereIn('id', $groups->keys())
            ->pluck('name', 'id');

        return $groups
            ->map(fn (Collection $lines, int $id): array => [
                'id' => $id,
                'name' => (string) $names[$id],
                'amount' => (float) $lines->sum('amount'),
                'isExcluded' => $exclusions->excludesExpense($id),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }
}
