<?php

namespace Database\Seeders;

use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceType;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class InvoiceSeeder extends Seeder
{
    /**
     * The path of the placeholder image every seeded invoice uses.
     */
    private const IMAGE = 'invoices/seeded-invoice.png';

    /**
     * Fake invoices for local development, on a fresh database after the users, descriptions, items, and suppliers are seeded:
     * general output and input invoices this month and last month, purchase invoices for the items, and a payoff on one of them.
     */
    public function run(): void
    {
        Storage::disk('public')->put(self::IMAGE, $this->drawPlaceholderImage());

        $user = User::query()->firstOrFail();
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $lastMonth = $thisMonth->subMonth();

        $this->generalInvoice($user, 'رواتب', 'رواتب الموظفين', $thisMonth->addDays(1), 3500000);
        $this->generalInvoice($user, 'ايجارات', 'ايجار المعمل', $thisMonth->addDays(2), 1200000);
        $this->generalInvoice($user, 'نثرية', 'قرطاسية', $thisMonth->addDays(3), 75000);
        $this->generalInvoice($user, 'تشغيلية', 'كهرباء', $lastMonth->addDays(9), 450000);
        $this->generalInvoice($user, 'ايرادات خارجية', 'بيع خردة', $thisMonth->addDays(4), 600000);
        $this->generalInvoice($user, 'استثمار', 'استثمار الشركاء', $lastMonth->addDays(4), 10000000);

        $supplier = Supplier::query()->firstOrFail();
        $items = Item::query()->with('specificDescription')->take(4)->get();

        $olderPurchase = $this->purchaseInvoice($user, $supplier, $lastMonth->addDays(14), [
            [$items[0], 10, 12, 1440000],
            [$items[1], 5, 20, 1500000],
        ]);

        $newerPurchase = $this->purchaseInvoice($user, $supplier, $thisMonth->addDays(5), [
            [$items[2], 4, 10, 800000],
            [$items[3], 2, 25, 650000],
        ]);

        // Both are in the stock, and a third one is on its way: the importer confirmed it and the receiver did not.
        $this->confirm($user, $olderPurchase, InvoiceConfirmation::Receiver);
        $this->confirm($user, $newerPurchase, InvoiceConfirmation::Receiver);

        $this->confirm($user, $this->purchaseInvoice($user, $supplier, $thisMonth->addDays(7), [
            [$items[0], 5, 12, 780000],
        ]), InvoiceConfirmation::Importer);

        Payoff::create([
            'invoice_id' => $olderPurchase->id,
            'amount' => 1000000,
            'date' => $thisMonth->addDays(6),
        ]);
    }

    /**
     * Confirms the invoice as the importer, and then as the receiver too when the receiver's confirmation is asked for.
     */
    private function confirm(User $user, Invoice $invoice, InvoiceConfirmation $upTo): void
    {
        $confirm = new ConfirmPurchaseInvoice;

        $confirm->handle($user, $invoice, (float) $invoice->amount, InvoiceConfirmation::Importer);

        if ($upTo === InvoiceConfirmation::Receiver) {
            $confirm->handle($user, $invoice->fresh(), (float) $invoice->amount, InvoiceConfirmation::Receiver);
        }
    }

    /**
     * Creates a general invoice under the named descriptions, with no supplier and one line of the given price.
     */
    private function generalInvoice(User $user, string $general, string $specific, CarbonImmutable $date, float $price): Invoice
    {
        $generalDescription = GeneralDescription::query()->where('name', $general)->firstOrFail();

        $invoice = Invoice::create([
            'name' => $specific,
            'type' => $generalDescription->type,
            'general_description_id' => $generalDescription->id,
            'user_id' => $user->id,
            'date' => $date,
            'images' => [self::IMAGE],
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $generalDescription->specificDescriptions()->where('name', $specific)->firstOrFail()->id,
            'price' => $price,
        ]);

        return $invoice;
    }

    /**
     * Creates a purchase invoice with one line per item: [item, boxes, box quantity, price].
     * Every carton holds one packet, and every carton is 0.05 CBM, about 50 × 40 × 25 cm. One CBM is shipped at $120.
     *
     * @param  list<array{Item, int, int, float}>  $lines
     */
    private function purchaseInvoice(User $user, Supplier $supplier, CarbonImmutable $date, array $lines): Invoice
    {
        $invoice = Invoice::create([
            'name' => 'مشتريات '.$supplier->name_ar,
            'type' => InvoiceType::Output,
            'general_description_id' => GeneralDescription::purchases()->id,
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'date' => $date,
            'cbm_price' => 120,
            'images' => [self::IMAGE],
        ]);

        foreach ($lines as [$item, $boxesCount, $boxQuantity, $price]) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'specific_description_id' => $item->specificDescription->id,
                'boxes_count' => $boxesCount,
                'box_quantity' => $boxQuantity,
                'boxes_per_carton' => 1,
                'carton_cbm' => 0.05,
                'price' => $price,
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Draws a plain paper-like placeholder for an invoice photo and returns the PNG bytes.
     */
    private function drawPlaceholderImage(): string
    {
        $image = imagecreatetruecolor(600, 800);
        $paper = imagecolorallocate($image, 250, 250, 245);
        $line = imagecolorallocate($image, 200, 200, 200);

        imagefilledrectangle($image, 0, 0, 600, 800, $paper);

        for ($y = 120; $y < 760; $y += 40) {
            imageline($image, 50, $y, 550, $y, $line);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
