<?php

namespace App\Filament\Resources\PurchaseInvoices\Actions;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Moves a purchase invoice to its next stage, one step forward, after a confirmation.
 * It shows only while there is a next stage, and only to a user allowed to edit the invoice. A refused move is shown as a notification.
 */
class AdvanceStageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'advanceStage';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (Invoice $record): string => __('filament/resources/purchase_invoice.actions.advance_stage.label', [
                'stage' => $record->stage?->next()?->getLabel() ?? '',
            ]))
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('primary')
            ->visible(fn (Invoice $record): bool => $record->stage?->next() !== null && (auth()->user()?->can('update', $record) ?? false))
            ->requiresConfirmation()
            ->modalHeading(fn (Invoice $record): string => __('filament/resources/purchase_invoice.actions.advance_stage.label', [
                'stage' => $record->stage?->next()?->getLabel() ?? '',
            ]))
            ->modalDescription(__('filament/resources/purchase_invoice.actions.advance_stage.description'))
            ->action(function (Invoice $record, Action $action): void {
                try {
                    $record->advanceStage();
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first())
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('filament/resources/purchase_invoice.actions.advance_stage.done', ['stage' => $record->stage->getLabel()]))
                    ->send();
            });
    }
}
