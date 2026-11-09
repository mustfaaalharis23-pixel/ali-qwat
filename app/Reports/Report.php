<?php

namespace App\Reports;

use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Metrics\Period;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The template of every report: the rows of a period, from its first day to its last, the totals of those rows,
 * and the same rows as an Excel file. A report gives its uriKey, its label, its columns, and its rows,
 * and may give its own filters (in $data), its totals, and who may open it.
 */
abstract class Report
{
    /**
     * The report's own filters, such as a sort choice.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * The first day of the fetched period, as Y-m-d.
     */
    public ?string $fetchedFrom = null;

    /**
     * The last day of the fetched period, as Y-m-d.
     */
    public ?string $fetchedUntil = null;

    /**
     * The rows of the fetched period, kept so the table and the totals do not query twice.
     *
     * @var list<array<string, mixed>>|null
     */
    protected ?array $cachedRows = null;

    /**
     * The unique key of the report, in kebab case, such as sales. It builds the address and the Excel file name.
     */
    abstract public static function uriKey(): string;

    /**
     * The name of the report, shown as the page title and the sidebar link.
     */
    abstract public static function label(): string;

    /**
     * The columns of the report table, each with its name in the rows and its heading. The headings head the Excel file.
     *
     * @return list<array{name: string, label: string}>
     */
    abstract public function columns(): array;

    /**
     * The rows of the report inside the period, each keyed by column name.
     *
     * @return list<array<string, mixed>>
     */
    abstract public function rows(Period $period): array;

    /**
     * Anyone signed in may open a report unless the report says otherwise.
     */
    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * The report fetched for the days from $from to $until (Y-m-d), with its own filters in $data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function open(string $from, string $until, array $data = []): static
    {
        $report = app(static::class);
        $report->data = $data;
        $report->fetchedFrom = $from;
        $report->fetchedUntil = $until;

        return $report;
    }

    /**
     * The totals of the report: the colored cards under the table, and the last lines of the Excel file.
     * A report has none unless it gives them, and a report that shows them its own way keeps only the Excel lines.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: float, color: string, currency?: Currency}>
     */
    public function totals(array $rows): array
    {
        return [];
    }

    /**
     * Whether the totals are shown as cards under the table. A report that says no keeps its totals for the Excel file only.
     */
    public function hasTotalsCards(): bool
    {
        return true;
    }

    /**
     * Whether a period has been fetched.
     */
    public function hasFetched(): bool
    {
        return $this->fetchedFrom !== null && $this->fetchedUntil !== null;
    }

    /**
     * The rows of the fetched period, worked out once. Nothing before a period is fetched.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchedRows(): array
    {
        if (! $this->hasFetched()) {
            return [];
        }

        return $this->cachedRows ??= $this->rows($this->fetchedPeriod());
    }

    /**
     * The fetched period, from the first second of its first day to the last second of its last day.
     * Only call it after a period is fetched.
     */
    public function fetchedPeriod(): Period
    {
        return new Period(
            CarbonImmutable::parse($this->fetchedFrom)->startOfDay(),
            CarbonImmutable::parse($this->fetchedUntil)->endOfDay(),
        );
    }

    /**
     * The totals of the fetched rows. Nothing when there are no rows.
     *
     * @return list<array{label: string, value: float, color: string, currency?: Currency}>
     */
    public function fetchedTotals(): array
    {
        $rows = $this->fetchedRows();

        return $rows === [] ? [] : $this->totals($rows);
    }

    /**
     * The totals of the fetched rows ready to be shown: each with its formatted number and its currency symbol.
     *
     * @return list<array{label: string, value: float, color: string, currency: string|null, formatted: array{number: string, currency: string|null}}>
     */
    public function formattedTotals(): array
    {
        return array_map(function (array $total): array {
            $value = FormattedValue::of((float) $total['value'], $total['currency'] ?? null);

            return [
                'label' => $total['label'],
                'value' => (float) $total['value'],
                'color' => $total['color'],
                'currency' => ($total['currency'] ?? null)?->value,
                'formatted' => ['number' => $value->number, 'currency' => $value->currency],
            ];
        }, $this->fetchedTotals());
    }

    /**
     * Downloads the fetched rows as an Excel file: the column headings, one line per row, then the totals.
     * In Arabic the sheet reads from right to left.
     */
    public function download(): StreamedResponse
    {
        $columns = $this->columns();
        $rows = $this->fetchedRows();
        $totals = $this->fetchedTotals();
        $isRightToLeft = app()->getLocale() === 'ar';

        return response()->streamDownload(function () use ($columns, $rows, $totals, $isRightToLeft): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setSheetView((new SheetView)->setRightToLeft($isRightToLeft));

            $writer->addRow(Row::fromValues(array_column($columns, 'label')));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn (array $column): mixed => $row[$column['name']] ?? null, $columns)));
            }

            if ($totals !== []) {
                $writer->addRow(Row::fromValues([]));

                foreach ($totals as $total) {
                    $writer->addRow(Row::fromValues([$total['label'], $total['value']]));
                }
            }

            $writer->close();
        }, static::uriKey()."_{$this->fetchedFrom}_{$this->fetchedUntil}.xlsx");
    }

    /**
     * A column of the report table.
     *
     * @return array{name: string, label: string}
     */
    protected function column(string $name, string $label): array
    {
        return ['name' => $name, 'label' => $label];
    }
}
