<?php

namespace App\Filament\Resources\PurchaseInvoices\Actions;

use App\Enums\InvoiceConfirmation;
use App\Filament\Forms\Components\MoneyInput;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a purchase invoice for the side that is next: the importer first, then the receiver.
 * It shows only for the side the invoice is waiting for, and only to a user allowed to confirm for that side,
 * so a super user sees it twice: once as the importer and once as the receiver.
 * The modal shows the invoice amount and asks for it to be written again, and a refused confirmation is shown as a notification.
 */
class ConfirmInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'confirmInvoice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (Invoice $record): string => __('filament/resources/purchase_invoice.actions.confirm.label', [
                'side' => $this->sideFor($record)?->getLabel() ?? '',
            ]))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Invoice $record): bool => $this->canConfirm($record))
            ->modalHeading(fn (Invoice $record): string => __('filament/resources/purchase_invoice.actions.confirm.heading', [
                'side' => $this->sideFor($record)?->getLabel() ?? '',
            ]))
            ->modalDescription(function (Invoice $record): string {
                $amount = FormattedValue::of((float) $record->amount, Currency::ChineseYuan);

                return __('filament/resources/purchase_invoice.actions.confirm.description', [
                    'amount' => "{$amount->number} {$amount->currency}",
                ]);
            })
            ->modalIcon(Heroicon::OutlinedCheckBadge)
            ->modalSubmitActionLabel(__('filament/resources/purchase_invoice.actions.confirm.submit'))
            ->schema([
                MoneyInput::make('amount')
                    ->label(__('filament/resources/purchase_invoice.actions.confirm.amount'))
                    ->suffix(Currency::ChineseYuan->symbol())
                    ->minValue(0.01)
                    ->required(),
            ])
            ->action(function (Invoice $record, array $data, Action $action): void {
                $side = $this->sideFor($record);

                try {
                    (new ConfirmPurchaseInvoice)->handle(auth()->user(), $record, (float) $data['amount'], $side);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first())
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('filament/resources/purchase_invoice.actions.confirm.done', ['side' => $side->getLabel()]))
                    ->send();
            });
    }

    /**
     * The side this invoice is waiting for, or null when both sides confirmed it.
     */
    private function sideFor(Invoice $invoice): ?InvoiceConfirmation
    {
        return $invoice->awaitedConfirmation();
    }

    /**
     * Whether the user can confirm this invoice for the side it is waiting for.
     */
    private function canConfirm(Invoice $invoice): bool
    {
        $side = $this->sideFor($invoice);

        return $side !== null && (auth()->user()?->can($side->ability(), $invoice) ?? false);
    }
}
