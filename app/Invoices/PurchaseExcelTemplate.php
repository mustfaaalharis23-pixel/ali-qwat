<?php

namespace App\Invoices;

use App\Admin\ExcelTemplate;

class PurchaseExcelTemplate
{
    public const string FILENAME = 'AKA-purchase-invoice-template.xlsx';

    public const string MARKER = 'AKA PURCHASE ORDER v1';

    public const int FIRST_DATA_ROW = ExcelTemplate::FIRST_DATA_ROW;

    public const array DEFAULT_LANGUAGES = ['en', 'zh_CN'];

    /** @return array{order_tab: string, order_title: string, start_row: string, guide_tab: string, guide_title: string, columns: array<string, string>, instructions: list<string>} */
    public static function translations(string $language): array
    {
        return __('purchase_excel', [], $language);
    }

    /** @param non-empty-list<string> $languages */
    public function write(string $path, array $languages = self::DEFAULT_LANGUAGES): void
    {
        (new ExcelTemplate)->write(
            $path,
            array_map(self::translations(...), $languages),
            $languages[0] === 'ar',
            [26, 26, 26, 26, 26, 26, 35, 35, 26, 26, 35],
            [0, 6, 7],
        );
    }
}
