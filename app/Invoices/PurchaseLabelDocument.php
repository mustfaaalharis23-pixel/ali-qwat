<?php

namespace App\Invoices;

use App\Metrics\FormattedValue;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LensPower;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Spatie\Browsershot\Browsershot;

/**
 * The carton label of one purchase invoice. It goes to the supplier's factory, which sticks it on the invoice's cartons.
 * The factory reads it, so it is in English and Chinese whatever the user's language, and it carries no price or cost.
 * Its barcode holds the label number, so scanning a carton finds its invoice.
 */
class PurchaseLabelDocument
{
    /**
     * The number printed big on the label and held by its barcode, such as PI-12.
     */
    public static function number(Invoice $invoice): string
    {
        return "PI-{$invoice->id}";
    }

    /**
     * The purchase invoice whose label holds this number, as read by a barcode scanner. Null when the text is not a label
     * number, or its invoice is not a purchase invoice or is deleted.
     */
    public static function invoiceOf(string $number): ?Invoice
    {
        if (preg_match('/^PI-(\d+)$/', $number, $matches) !== 1) {
            return null;
        }

        return Invoice::query()->purchases()->find((int) $matches[1]);
    }

    /**
     * The label as a page. Every carton of the invoice carries the same label, so it lists the lens powers of all its lines,
     * from the lowest, when it has any.
     */
    public function view(Invoice $invoice): View
    {
        $invoice->loadMissing(['supplier', 'category', 'items.groups.lensPower']);

        return view('invoices.label.purchase', [
            'invoice' => $invoice,
            'number' => self::number($invoice),
            'barcode' => $this->barcode(self::number($invoice)),
            'cartons' => FormattedValue::of((float) $invoice->items->sum('cartons_count'))->number,
            'cbm' => FormattedValue::of($invoice->cbm)->number,
            'powers' => $invoice->items
                ->flatMap(fn (InvoiceItem $line): Collection => $line->groups->pluck('lensPower'))
                ->filter()
                ->unique('id')
                ->sortBy(fn (LensPower $power): float => (float) $power->value)
                ->map(fn (LensPower $power): string => $power->inHundreds())
                ->values()
                ->all(),
        ]);
    }

    /**
     * The Code 128 barcode of the number as an inline SVG, stretched to the width of the label so it is easy to scan.
     */
    private function barcode(string $number): string
    {
        $svg = (new BarcodeGeneratorSVG)->getBarcode($number, BarcodeGeneratorSVG::TYPE_CODE_128, 3, 90);

        return str_replace('<svg ', '<svg preserveAspectRatio="none" ', substr($svg, strpos($svg, '<svg ')));
    }

    /**
     * The label as a PDF file of 100 × 150 mm, the size of a shipping label, rendered by a real browser.
     *
     * @return string the bytes of the file
     */
    public function pdf(Invoice $invoice): string
    {
        $browsershot = Browsershot::html($this->view($invoice)->render())
            ->showBackground()
            ->paperSize(100, 150)
            ->margins(0, 0, 0, 0)
            ->timeout(60);

        if (is_string($chrome = config('services.chrome.path')) && $chrome !== '') {
            $browsershot->setChromePath($chrome);
        }

        return $browsershot->pdf();
    }
}
