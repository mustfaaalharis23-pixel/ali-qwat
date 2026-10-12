<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Invoices\FetchExchangeRate;
use App\Models\GeneralDescription;
use App\Models\Payoff;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    /**
     * The page uses the full width, so the lines table has room for wide fields.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * What was paid to the supplier on the spot, taken off the form and turned into the invoice's first payoff.
     */
    private ?float $initialPayment = null;

    /**
     * The exchange rate and its source are filled from the banks when the page opens: the Agricultural Bank of China first,
     * then China Merchants Bank. When neither answers, the field stays empty and the user is told to type the rate by hand.
     */
    protected function afterFill(): void
    {
        $fetched = (new FetchExchangeRate)->handle();

        if ($fetched !== null) {
            $this->data['exchange_rate'] = $fetched['rate'];
            $this->data['exchange_rate_source'] = $fetched['source']->value;

            return;
        }

        Notification::make()
            ->title(__('filament/resources/purchase_invoice.notifications.exchange_rate_unavailable.title'))
            ->body(__('filament/resources/purchase_invoice.notifications.exchange_rate_unavailable.body'))
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * The importer's confirmation is not a step anyone takes by hand any more: whoever creates the invoice is its
     * importer. This runs after the lines are saved, so the invoice already carries its amount when it is confirmed.
     */
    protected function afterCreate(): void
    {
        (new ConfirmPurchaseInvoice)->handleOnCreation($this->getRecord());
        $this->payTheInitialPayment();
    }

    /**
     * The initial payment is a payoff marked as the initial payment: it carries the invoice's own date, the payoff's own
     * rules (a positive amount that never adds up to more than the invoice) guard it, and it can never be changed or deleted on its own.
     */
    private function payTheInitialPayment(): void
    {
        if ($this->initialPayment === null || $this->initialPayment <= 0) {
            return;
        }

        $invoice = $this->getRecord();

        Payoff::create([
            'invoice_id' => $invoice->getKey(),
            'amount' => $this->initialPayment,
            'date' => $invoice->date,
            'is_initial_payment' => true,
        ]);
    }

    /**
     * A purchase invoice is always under the purchases description, of type output, and the user who creates it is its creator.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $purchases = GeneralDescription::purchases();

        $data['general_description_id'] = $purchases->id;
        $data['type'] = $purchases->type;
        $data['user_id'] = auth()->id();

        // Not a column of the invoice: it is held here and paid once the invoice exists.
        $payment = $data['initial_payment'] ?? null;
        unset($data['initial_payment']);
        $this->initialPayment = blank($payment) ? null : (float) str_replace(',', '', (string) $payment);

        return $data;
    }
}
