<?php

namespace App\Admin;

use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

class ExcelTemplate
{
    public const int FIRST_DATA_ROW = 4;

    /**
     * @param  non-empty-list<array{order_tab: string, order_title: string, start_row: string, guide_tab: string, guide_title: string, columns: array<string, string>, instructions: list<string>}>  $translations
     * @param  list<int>  $columnWidths
     * @param  list<int>  $textColumns  Zero-based column indexes that must keep leading zeroes or symbols.
     */
    public function write(string $path, array $translations, bool $rightToLeft, array $columnWidths, array $textColumns): void
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

            $blankValues = array_replace(array_fill(0, count($headers), null), array_fill_keys($textColumns, ' '));
            $blank = Row::fromValuesWithStyles($blankValues, columnStyles: array_fill_keys($textColumns, (new Style)->setFormat('@')));
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
