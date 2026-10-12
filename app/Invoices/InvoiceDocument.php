<?php

namespace App\Invoices;

use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Spatie\Browsershot\Browsershot;

/**
 * The printable invoice of one order. There is one document only: the panel prints it from the screen and the
 * application shares it as a PDF, both from the same templates, so a change to a template reaches the two of them.
 *
 * a4 is the full page invoice and pos is the receipt of the small 80mm printer.
 */
class InvoiceDocument
{
    /**
     * The invoice as a page. It is the customer's invoice: what the order cost us, its profit, and its margin
     * are never on it, so no caller can ask for them.
     */
    public function view(Order $order, string $format = 'a4'): View
    {
        $order->loadMissing(['customer', 'cashier', 'items.item']);

        return view("invoices.print.{$format}", [
            'order' => $order,
            'money' => fn (float $amount): string => FormattedValue::of($amount, Currency::IraqiDinar)->number,
            'currency' => Currency::IraqiDinar->symbol(),
            'fonts' => $this->fontFaces(),
        ]);
    }

    /**
     * The invoice as a PDF file, rendered by a real browser so the page comes out exactly as it looks on the screen.
     * It goes to the customer, like the printed paper.
     *
     * @return string the bytes of the file
     */
    public function pdf(Order $order, string $format = 'a4'): string
    {
        $browsershot = Browsershot::html($this->view($order, $format)->render())
            ->showBackground()
            ->timeout(60);

        if (is_string($chrome = config('services.chrome.path')) && $chrome !== '') {
            $browsershot->setChromePath($chrome);
        }

        $format === 'pos'
            ? $browsershot->paperSize(80, 297)->margins(0, 0, 0, 0)
            : $browsershot->format('A4');

        return $browsershot->pdf();
    }

    /**
     * The invoice's font, written into the page itself: every font file named in public/fonts/invoice/font.css
     * is replaced by its contents. So the page on the screen and the PDF use the same font without a network,
     * and the PDF needs no file link, which the browser that makes it refuses.
     */
    private function fontFaces(): string
    {
        $directory = public_path('fonts/invoice');

        return preg_replace_callback(
            '/url\(([^)]+\.woff2)\)/',
            fn (array $match): string => 'url(data:font/woff2;base64,'.base64_encode(file_get_contents("{$directory}/{$match[1]}")).')',
            file_get_contents("{$directory}/font.css"),
        );
    }
}
