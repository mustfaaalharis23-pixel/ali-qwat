<?php

namespace App\Filament\Reports\ProfitAndLoss;

use App\Filament\Reports\Report;
use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
use App\Metrics\Currency;
use App\Metrics\Exclusions;
use App\Metrics\Period;
use App\Metrics\ProfitAndLoss;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The profit and loss report: what the period earned, where it earned it, where it spent it, and the proof that the
 * numbers explain each other.
 *
 * The period of the report is a whole month, so the report opens on the month that just ended and shows it right away:
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
 * and the screen is drawn by the statement view. The table of the report template is not shown; it only feeds the
 * Excel file, which holds the same statement as one flat sheet.
 */
class ProfitAndLossReport extends Report
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    /**
     * The first link of the accounting group: the report comes before the screens it reads from.
     */
    protected static ?int $navigationSort = 1;

    /**
     * The oldest year the picker can go back to.
     */
    protected const FIRST_YEAR = 2026;

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

    /**
     * It sits with the accounting screens it reads from, not with the other reports.
     */
    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.accounting');
    }

    public static function uriKey(): string
    {
        return 'profit-and-loss';
    }

    public static function label(): string
    {
        return __('filament/reports/profit_and_loss.title');
    }

    /**
     * The report opens on the month that just ended, already fetched, so it says something the moment it is opened.
     */
    public function mount(): void
    {
        $this->month = $this->defaultMonth()->format('Y-m');

        $this->fetch();
    }

    /**
     * Fetches the chosen month, from its first day to its last.
     */
    public function fetch(): void
    {
        $month = CarbonImmutable::parse($this->month.'-01');

        $this->fetchedFrom = $month->startOfMonth()->toDateString();
        $this->fetchedUntil = $month->endOfMonth()->toDateString();

        $this->statement = null;
        $this->cachedRows = null;
        $this->resetTable();
    }

    /**
     * Chooses a month from the picker and fetches it at once, even when it is the month that is already shown,
     * so the same button is also how the report is refreshed.
     * The month comes from the browser, so a month that is not a real month, or one that has not begun,
     * is left alone and nothing happens.
     */
    public function selectMonth(string $month): void
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return;
        }

        $chosen = CarbonImmutable::parse($month.'-01');

        if ($chosen->greaterThan(CarbonImmutable::now()->startOfMonth()) || $chosen->year < self::FIRST_YEAR) {
            return;
        }

        $this->month = $month;

        $this->fetch();
    }

    /**
     * Leaves a revenue out of the totals, or puts it back.
     */
    public function toggleRevenue(int $specificDescriptionId): void
    {
        $this->excludedRevenues = $this->toggled($this->excludedRevenues, $specificDescriptionId);

        $this->statement = null;
    }

    /**
     * Leaves the expenses of a general description out of the totals, or puts them back.
     */
    public function toggleExpense(int $generalDescriptionId): void
    {
        $this->excludedExpenses = $this->toggled($this->excludedExpenses, $generalDescriptionId);

        $this->statement = null;
    }

    /**
     * Puts everything that was left out back, so the report reads the whole month again.
     */
    public function clearExclusions(): void
    {
        $this->excludedRevenues = [];
        $this->excludedExpenses = [];

        $this->statement = null;
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
     * Downloading the Excel file of the fetched month, from the page header.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearExclusions')
                ->label(__('filament/reports/profit_and_loss.exclusions.clear'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->outlined()
                ->visible(fn (): bool => $this->hasExclusions())
                ->action(fn () => $this->clearExclusions()),
            Action::make('downloadExcel')
                ->label(__('filament/reports.actions.download_excel'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->outlined()
                ->action(fn (): StreamedResponse => $this->downloadExcel()),
        ];
    }

    /**
     * The report has no period form: its month picker is the period, and it is drawn by its own view.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * The month, then the four cards of the month, then the statement.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.reports.profit-and-loss.month-picker')
                    ->viewData(fn (): array => [
                        'month' => $this->month,
                        'monthNames' => $this->monthNames(),
                        'firstYear' => self::FIRST_YEAR,
                    ]),
                View::make('filament.reports.totals')
                    ->viewData(fn (): array => ['totals' => $this->fetchedTotals()])
                    ->visible(fn (): bool => $this->fetchedTotals() !== []),
                View::make('filament.reports.profit-and-loss.statement')
                    ->viewData(fn (): array => [
                        'statement' => $this->fetchedStatement(),
                        'report' => $this,
                    ]),
            ]);
    }

    /**
     * The columns of the Excel file: the section, the line, its details, and its amount.
     */
    public function columns(): array
    {
        return [
            TextColumn::make('section')
                ->label(__('filament/reports/profit_and_loss.columns.section')),
            TextColumn::make('detail')
                ->label(__('filament/reports/profit_and_loss.columns.detail')),
            TextColumn::make('note')
                ->label(__('filament/reports/profit_and_loss.columns.note')),
            TextColumn::make('amount')
                ->label(__('filament/reports/profit_and_loss.columns.amount'))
                ->numeric(),
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
     * The address of the invoice of an expense line, which the statement view opens in a new tab.
     */
    public function invoiceUrl(int $invoiceId): string
    {
        return GeneralInvoiceResource::getUrl('view', ['record' => $invoiceId]);
    }

    /**
     * The statement of the fetched period, or nothing before a period is fetched.
     */
    protected function fetchedStatement(): ?ProfitAndLoss
    {
        if (! $this->hasFetched()) {
            return null;
        }

        return $this->statement ??= ProfitAndLoss::for($this->fetchedPeriod(), $this->exclusions());
    }

    /**
     * The list with that id taken out of it, or added to it when it was not there.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function toggled(array $ids, int $id): array
    {
        return in_array($id, $ids, true)
            ? array_values(array_diff($ids, [$id]))
            : [...$ids, $id];
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
