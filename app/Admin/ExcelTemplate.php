<?php

namespace App\Admin;

use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

class ExcelTemplate
{
    public const int FIRST_DATA_ROW = 4;

    /** @param array<int|string, mixed> $titleRow */
    public static function hasLocalizedTitle(array $titleRow, string $translationGroup): bool
    {
        return array_any(array_keys(config('app.locales')), fn (string $locale): bool => in_array(
            __("{$translationGroup}.order_title", [], $locale),
            $titleRow,
            true,
        ));
    }

    /**
     * @param  non-empty-list<array{order_tab: string, order_title: string, start_row: string, guide_tab: string, guide_title: string, columns: array<string, string>, instructions: list<string>}>  $translations
     * @param  list<int>  $columnWidths
     * @param  list<int>  $textColumns  Zero-based column indexes that must keep leading zeroes or symbols.
     * @param  int|null  $fillRowHeight  A taller height for the rows to fill, with their text centered vertically.
     */
    public function write(string $path, array $translations, bool $rightToLeft, array $columnWidths, array $textColumns, ?int $fillRowHeight = null): void
    {
        $localizedColumns = array_column($translations, 'columns');
        $headers = array_map(
            fn (string $column): string => implode("\n", array_column($localizedColumns, $column)),
            array_keys($localizedColumns[0]),
        );
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet()->setName(implode(' - ', array_column($translations, 'order_tab')));
            $sheet->setSheetView((new SheetView)->setFreezeRow(self::FIRST_DATA_ROW)->setRightToLeft($rightToLeft));
            foreach ($columnWidths as $index => $width) {
                $sheet->setColumnWidth($width, $index + 1);
            }

            $heading = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('173D38');
            $hint = (new Style)->setFontColor('173D38')->setBackgroundColor('DFEDE9');
            $writer->addRow(Row::fromValues(array_column($translations, 'order_title'), $heading)->setHeight(25));
            $writer->addRow(Row::fromValues($headers, (clone $heading)->setShouldWrapText())->setHeight(22 * count($translations)));
            $writer->addRow(Row::fromValues(array_column($translations, 'start_row'), $hint)->setHeight(23));

            $blank = $this->fillRow(count($headers), $textColumns, $fillRowHeight);
            for ($row = 0; $row < 200; $row++) {
                $writer->addRow($blank);
            }

            $guide = $writer->addNewSheetAndMakeItCurrent()->setName(implode(' - ', array_column($translations, 'guide_tab')));
            $guide->setSheetView((new SheetView)->setRightToLeft($rightToLeft));
            $guide->setColumnWidthForRange(65, 1, count($translations));
            $writer->addRow(Row::fromValues(array_column($translations, 'guide_title'), $heading)->setHeight(26));

            $wrapped = (new Style)->setShouldWrapText();
            $steps = array_column($translations, 'instructions');
            foreach (array_keys($steps[0]) as $step) {
                $writer->addRow(Row::fromValues(array_column($steps, $step), $wrapped)->setHeight(55));
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * An empty row to fill. A tall row holds a space in every cell, because the writer drops the alignment of an empty cell.
     *
     * @param  list<int>  $textColumns
     */
    private function fillRow(int $columnCount, array $textColumns, ?int $height): Row
    {
        $textStyle = (new Style)->setFormat('@');
        if ($height === null) {
            $values = array_replace(array_fill(0, $columnCount, null), array_fill_keys($textColumns, ' '));

            return Row::fromValuesWithStyles($values, columnStyles: array_fill_keys($textColumns, $textStyle));
        }

        $textStyle->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $centered = (new Style)->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $styles = array_replace(array_fill(0, $columnCount, $centered), array_fill_keys($textColumns, $textStyle));

        return Row::fromValuesWithStyles(array_fill(0, $columnCount, ' '), columnStyles: $styles)->setHeight($height);
    }

    /**
     * @param  array<int|string, mixed>  $headers
     * @param  list<array<string, string>>  $localizedColumns
     * @return array<string, int|string>
     */
    public static function columnPositions(array $headers, array $localizedColumns, string $error): array
    {
        $expected = [];
        foreach ($localizedColumns as $labels) {
            foreach ($labels as $field => $label) {
                $expected[mb_strtolower($label)] = $field;
            }
        }

        $columns = [];
        foreach ($headers as $position => $header) {
            if (! is_string($header)) {
                continue;
            }

            $matches = [];
            foreach (preg_split('/\R/u', $header) ?: [] as $label) {
                $field = $expected[mb_strtolower(trim($label))] ?? null;
                if ($field !== null) {
                    $matches[$field] = true;
                }
            }

            $field = array_key_first($matches);
            if ($field === null) {
                continue;
            }
            if (count($matches) > 1 || isset($columns[$field])) {
                throw ValidationException::withMessages(['file' => $error]);
            }

            $columns[$field] = $position;
        }

        return $columns;
    }
}
