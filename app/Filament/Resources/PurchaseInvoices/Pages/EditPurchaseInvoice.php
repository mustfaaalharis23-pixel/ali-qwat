<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    /**
     * The page uses the full width, so the lines table has room for wide fields.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * An invoice that has payoffs cannot be deleted, so its delete actions are hidden.
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn (Invoice $record): bool => $record->payoffs()->exists()),
            ForceDeleteAction::make()
                ->hidden(fn (Invoice $record): bool => $record->payoffs()->withTrashed()->exists()),
            RestoreAction::make(),
        ];
    }

    /**
     * The lines are saved before the invoice on the edit page, so the amounts are recalculated once more after saving.
     */
    protected function afterSave(): void
    {
        $this->record->refresh()->recalculateAmounts();
    }
}
