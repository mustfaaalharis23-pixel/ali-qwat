<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Filament\Reports\Report;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use Closure;
use Filament\Tables\Columns\Column;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs one of the panel's reports outside the panel, so the admin application shows exactly what the panel showed.
 *
 * The report class keeps every calculation: its rows, its totals, and its own helpers. The runner only fills the state
 * the report reads (the fetched period and the report's own filters) and calls the report's methods, including the
 * protected ones the panel calls from inside the page, so no calculation is written twice.
 *
 * @template TReport of Report
 */
final class ReportRunner
{
    /**
     * @param  TReport  $report
     */
    private function __construct(public readonly Report $report) {}

    /**
     * The report fetched for the days from $from to $until (Y-m-d), with its own filters in $data.
     *
     * @template TClass of Report
     *
     * @param  class-string<TClass>  $class
     * @param  array<string, mixed>  $data
     * @return self<TClass>
     */
    public static function open(string $class, string $from, string $until, array $data = []): self
    {
        $report = app($class);
        $report->data = $data;
        $report->fetchedFrom = $from;
        $report->fetchedUntil = $until;

        return new self($report);
    }

    /**
     * Calls a method of the report, even a protected or a private one, as the page itself calls it.
     */
    public function call(string $method, mixed ...$arguments): mixed
    {
        $call = Closure::bind(fn (mixed ...$arguments): mixed => $this->{$method}(...$arguments), $this->report, $this->report::class);

        return $call(...$arguments);
    }

    /**
     * The rows of the fetched period, worked out once.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->call('fetchedRows');
    }

    /**
     * The totals of the fetched rows, empty when there are no rows.
     *
     * @return list<array{label: string, value: float, color: string, currency?: Currency}>
     */
    public function totals(): array
    {
        return $this->call('fetchedTotals');
    }

    /**
     * The totals ready to be shown: each with its formatted number and its currency symbol.
     *
     * @return list<array{label: string, value: float, color: string, currency: string|null, formatted: array{number: string, currency: string|null}}>
     */
    public function formattedTotals(): array
    {
        return self::format($this->totals());
    }

    /**
     * @param  list<array{label: string, value: float, color: string, currency?: Currency}>  $totals
     * @return list<array{label: string, value: float, color: string, currency: string|null, formatted: array{number: string, currency: string|null}}>
     */
    public static function format(array $totals): array
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
        }, $totals);
    }

    /**
     * Whether the report shows its totals as cards under its table.
     */
    public function hasTotalsCards(): bool
    {
        return $this->call('hasTotalsCards');
    }

    /**
     * The columns of the report table: each one's name and its heading.
     *
     * @return list<array{name: string, label: string}>
     */
    public function columns(): array
    {
        return array_map(fn (Column $column): array => [
            'name' => $column->getName(),
            'label' => (string) $column->getLabel(),
        ], $this->report->columns());
    }

    /**
     * Downloads the fetched rows as an Excel file, as the panel did: the column headings, one line per row, then the
     * totals. In Arabic the sheet reads from right to left.
     */
    public function download(): StreamedResponse
    {
        $columns = $this->report->columns();
        $rows = $this->rows();
        $totals = $this->totals();
        $isRightToLeft = __('filament-panels::layout.direction') === 'rtl';
        $fileName = $this->report::uriKey()."_{$this->report->fetchedFrom}_{$this->report->fetchedUntil}.xlsx";

        return response()->streamDownload(function () use ($columns, $rows, $totals, $isRightToLeft): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setSheetView((new SheetView)->setRightToLeft($isRightToLeft));

            $writer->addRow(Row::fromValues(array_map(fn (Column $column): string => (string) $column->getLabel(), $columns)));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn (Column $column): mixed => $row[$column->getName()] ?? null, $columns)));
            }

            if ($totals !== []) {
                $writer->addRow(Row::fromValues([]));

                foreach ($totals as $total) {
                    $writer->addRow(Row::fromValues([$total['label'], $total['value']]));
                }
            }

            $writer->close();
        }, $fileName);
    }
}
