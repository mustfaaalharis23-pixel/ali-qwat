<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Invoices\FetchExchangeRate;
use App\Models\GeneralDescription;
use App\Models\Payoff;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;

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
     * Whether the form was filled from the copy this browser kept of an invoice that was being typed, so it can be discarded.
     */
    public bool $hasRestoredDraft = false;

    /**
     * Puts back what the user was typing before the page was left, from the copy this browser keeps under the user.
     * Only the form's own fields are taken, and never the images, because an upload does not outlive the page.
     * The copy is the form's raw state, so it is put back the way Filament keeps the form when creating another, without the hydration hooks.
     *
     * @param  array<string, mixed>  $draft
     */
    public function restoreDraft(array $draft): void
    {
        $rawState = $this->form->getRawState();
        $draft = Arr::except(array_intersect_key($draft, $rawState), ['images']);

        if ($draft === []) {
            return;
        }

        $this->form->rawState([...$rawState, ...$draft]);

        $hydratedDefaultState = null;
        $this->form->hydrateState($hydratedDefaultState, shouldCallHydrationHooks: false);
        $this->form->dispatchClientSideStateReset();

        $this->hasRestoredDraft = true;

        Notification::make()
            ->title(__('filament/resources/purchase_invoice.notifications.draft_restored.title'))
            ->body(__('filament/resources/purchase_invoice.notifications.draft_restored.body'))
            ->info()
            ->send();
    }

    /**
     * Keeps a copy of the form in this browser while it is typed, and brings it back when the page opens again.
     */
    public function getFooter(): View
    {
        return view('filament.purchase-invoices.draft', [
            'storageKey' => 'waqt:purchase-invoice-draft:'.auth()->id(),
        ]);
    }

    /**
     * Only after a copy was put back: discarding it forgets the copy and opens the page again on an empty invoice.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('discardDraft')
                ->label(__('filament/resources/purchase_invoice.actions.discard_draft.label'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('filament/resources/purchase_invoice.actions.discard_draft.heading'))
                ->modalDescription(__('filament/resources/purchase_invoice.actions.discard_draft.description'))
                ->modalSubmitActionLabel(__('filament/resources/purchase_invoice.actions.discard_draft.submit'))
                ->visible(fn (): bool => $this->hasRestoredDraft)
                ->action(function (): void {
                    $this->dispatch('purchase-invoice-draft-cleared');
                    $this->redirect(PurchaseInvoiceResource::getUrl('create'));
                }),
        ];
    }

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

        // Last, so the copy kept in the browser is forgotten only once the invoice is really created.
        $this->hasRestoredDraft = false;
        $this->dispatch('purchase-invoice-draft-cleared');
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
