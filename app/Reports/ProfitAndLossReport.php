<?php

namespace App\Reports;

use App\Metrics\Currency;
use App\Metrics\Exclusions;
use App\Metrics\Period;
use App\Metrics\ProfitAndLoss;
use Carbon\CarbonImmutable;

/**
 * The profit and loss report: what the period earned, where it earned it, where it spent it, and the proof that the
 * numbers explain each other.
 *
 * The period of the report is a whole month ($month), and the report opens on the month that just ended:
 * a profit and loss of a part of a month says nothing, and the month that is still running is never finished.
 * The page is read from the top down: the month picker, then the four cards of the month (sales, purchases, the profit
 * of the sales, and its margin), then the two tables
 * side by side that say where the money came from and where it went, and the summary that says whether it all adds up.
 * Any description can be left out with the box next to it, to read the month without it. What is left out stays on
 * the screen, crossed out, and only the totals skip it; while anything is left out the summary does not say whether
 * the report matches, because the profit would then be of a part of the month only.
 * The revenue side is the profit the sales froze the moment they were sold, plus the external revenues.
 * The expense side is the operating expenses, one row per general description, and every line under it opens in place
 * with a link to its own invoice.
 * The summary puts the net profit against what the company is really worth: what it was worth at the start of the
 * period, plus the net profit, plus the capital the partners moved, must be what it is worth at the end. When it is
 * not, the report says so, and the two value rows open what they are made of.
 *
 * The numbers are not worked out here. They come from App\Metrics\ProfitAndLoss and App\Metrics\CompanyValue,
 * and the admin application draws the statement itself. The rows of the report template only feed the
 * Excel file, which holds the same statement as one flat sheet.
 */
class ProfitAndLossReport extends Report
{
    /**
     * The oldest year the picker can go back to.
     */
    public const FIRST_YEAR = 2026;

    /**
     * The chosen month, as Y-m. It is the whole period of the report.
     */
    public ?string $month = null;

    /**
     * The specific descriptions of the revenues that are left out of the totals.
     *
     * @var list<int>
     */
    public array $excludedRevenues = [];

    /**
     * The general descriptions of the expenses that are left out of the totals.
     *
     * @var list<int>
     */
    public array $excludedExpenses = [];

    /**
     * The statement of the fetched period, worked out once per request.
     */
    protected ?ProfitAndLoss $statement = null;

    public static function uriKey(): string
    {
        return 'profit-and-loss';
    }

    public static function label(): string
    {
        return __('filament/reports/profit_and_loss.title');
    }

    /**
     * Whether anything is left out of the totals right now.
     */
    public function hasExclusions(): bool
    {
        return $this->exclusions()->any();
    }

    /**
     * What is left out of the totals right now.
     */
    public function exclusions(): Exclusions
    {
        return new Exclusions(
            array_values(array_map('intval', $this->excludedRevenues)),
            array_values(array_map('intval', $this->excludedExpenses)),
        );
    }

    /**
     * The month the report opens on: the one that just ended.
     */
    public function defaultMonth(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMonth()->subMonth();
    }

    /**
     * The names of the twelve months in the language of the panel, from January to December.
     *
     * @return list<string>
     */
    public function monthNames(): array
    {
        $locale = app()->getLocale();

        return collect(range(1, 12))
            ->map(fn (int $month): string => CarbonImmutable::create(2026, $month, 1)->locale($locale)->monthName)
            ->all();
    }

    /**
     * The name of the fetched month, shown under the title of the page, such as August 2026.
     */
    public function getSubheading(): string
    {
        return CarbonImmutable::parse($this->fetchedFrom)
            ->locale(app()->getLocale())
            ->isoFormat('MMMM YYYY');
    }

    /**
     * The columns of the Excel file: the section, the line, its details, and its amount.
     */
    public function columns(): array
    {
        return [
            $this->column('section', __('filament/reports/profit_and_loss.columns.section')),
            $this->column('detail', __('filament/reports/profit_and_loss.columns.detail')),
            $this->column('note', __('filament/reports/profit_and_loss.columns.note')),
            $this->column('amount', __('filament/reports/profit_and_loss.columns.amount')),
        ];
    }

    /**
     * The whole statement as one flat list, for the Excel file: the revenues, then the expenses with their lines,
     * then the summary.
     */
    public function rows(Period $period): array
    {
        $statement = $this->statement = ProfitAndLoss::for($period, $this->exclusions());
        $revenue = __('filament/reports/profit_and_loss.sections.revenue');
        $expenses = __('filament/reports/profit_and_loss.sections.expenses');
        $summary = __('filament/reports/profit_and_loss.sections.summary');

        $excluded = __('filament/reports/profit_and_loss.exclusions.excluded');
        $rows = [$this->row($revenue, __('filament/reports/profit_and_loss.rows.sales_profit'), $statement->salesProfit)];

        foreach ($statement->externalRevenues as $externalRevenue) {
            $rows[] = $this->row($revenue, $externalRevenue['name'], $externalRevenue['amount'], $externalRevenue['isExcluded'] ? $excluded : null);
        }

        $rows[] = $this->row($revenue, __('filament/reports/profit_and_loss.rows.total_revenue'), $statement->totalRevenue());

        foreach ($statement->expenseGroups as $group) {
            $rows[] = $this->row($expenses, $group['name'], $group['amount'], $group['isExcluded'] ? $excluded : null);

            foreach ($statement->linesOf($group['id']) as $line) {
                $rows[] = $this->row($expenses, $line['name'], $line['amount'], "{$line['invoice_name']} · {$line['invoice_date']}");
            }
        }

        $rows[] = $this->row($expenses, __('filament/reports/profit_and_loss.rows.total_expenses'), $statement->totalExpenses());
        $rows[] = $this->row($summary, __('filament/reports/profit_and_loss.rows.net_profit'), $statement->netProfit());
        $rows[] = $this->row($summary, __('filament/reports/profit_and_loss.rows.capital_movement'), $statement->capitalMovement);
        $rows[] = $this->row($summary, __('filament/reports/profit_and_loss.rows.value_at_start'), $statement->valueAtStart->total());
        $rows[] = $this->row($summary, __('filament/reports/profit_and_loss.rows.computed_value'), $statement->computedValue());
        $rows[] = $this->row($summary, __('filament/reports/profit_and_loss.rows.value_at_end'), $statement->valueAtEnd->total());

        $rows[] = $this->row(
            $summary,
            __('filament/reports/profit_and_loss.rows.difference'),
            $statement->difference(),
            $statement->checksItself()
                ? __('filament/reports/profit_and_loss.rows.'.($statement->isMatched() ? 'matched' : 'not_matched'))
                : __('filament/reports/profit_and_loss.exclusions.not_checked'),
        );

        return $rows;
    }

    /**
     * The four cards of the month, in the order they are read: what was sold, what was bought, the profit the sales froze
     * the moment they were sold (it has nothing to do with the purchases), and the margin the month sold at.
     */
    public function totals(array $rows): array
    {
        $statement = $this->fetchedStatement();

        if ($statement === null) {
            return [];
        }

        return [
            [
                'label' => __('filament/reports/profit_and_loss.totals.sales'),
                'value' => $statement->salesAmount,
                'color' => 'info',
                'currency' => Currency::IraqiDinar,
            ],
            [
                'label' => __('filament/reports/profit_and_loss.totals.purchases'),
                'value' => $statement->purchasesAmount,
                'color' => 'warning',
                'currency' => Currency::IraqiDinar,
            ],
            [
                'label' => __('filament/reports/profit_and_loss.totals.sales_profit'),
                'value' => $statement->salesProfit,
                'color' => $statement->salesProfit < 0 ? 'danger' : 'success',
                'currency' => Currency::IraqiDinar,
            ],
            [
                'label' => __('filament/reports/profit_and_loss.totals.sales_margin'),
                'value' => $statement->salesMargin(),
                'color' => $statement->salesMargin() < 0 ? 'danger' : 'success',
            ],
        ];
    }

    /**
     * The statement of the fetched period, or nothing before a period is fetched.
     */
    public function fetchedStatement(): ?ProfitAndLoss
    {
        if (! $this->hasFetched()) {
            return null;
        }

        return $this->statement ??= ProfitAndLoss::for($this->fetchedPeriod(), $this->exclusions());
    }

    /**
     * One row of the Excel file.
     *
     * @return array<string, mixed>
     */
    private function row(string $section, string $detail, ?float $amount, ?string $note = null): array
    {
        return [
            'section' => $section,
            'detail' => $detail,
            'note' => $note,
            'amount' => $amount,
        ];
    }
}
