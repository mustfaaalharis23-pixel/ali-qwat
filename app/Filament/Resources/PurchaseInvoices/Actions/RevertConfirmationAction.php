<?php

namespace App\Filament\Resources\PurchaseInvoices\Actions;

use App\Enums\InvoiceConfirmation;
use App\Invoices\RevertInvoiceConfirmation;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Takes back the last confirmation of a purchase invoice. It shows only to a super user, and only on a confirmed invoice.
 */
class RevertConfirmationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revertConfirmation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament/resources/purchase_invoice.actions.revert_confirmation.label'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->superOnly()
            ->visible(fn (Invoice $record): bool => auth()->user()?->is_super === true
                && $record->awaitedConfirmation() !== InvoiceConfirmation::Importer)
            ->requiresConfirmation()
            ->modalHeading(__('filament/resources/purchase_invoice.actions.revert_confirmation.label'))
            ->modalDescription(__('filament/resources/purchase_invoice.actions.revert_confirmation.description'))
            ->action(function (Invoice $record, Action $action): void {
                try {
                    (new RevertInvoiceConfirmation)->handle(auth()->user(), $record);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first())
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('filament/resources/purchase_invoice.actions.revert_confirmation.done'))
                    ->send();
            });
    }
}
