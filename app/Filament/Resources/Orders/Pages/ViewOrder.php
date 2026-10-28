<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Pages\InvoicePrinting;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * The page uses the full width, so the lines table has room.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * The order's invoice opens in a new tab to print, as a full page or as the small printer's receipt, the same as on the
     * invoice printing page. It shows to a user who can open that page, and not on a deleted order, which has no invoice.
     * A deleted order is not edited.
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('printA4')
                    ->label(__('filament/pages/invoice_printing.actions.print_a4'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (Order $record): string => route('invoices.print', ['order' => $record, 'format' => 'a4']))
                    ->openUrlInNewTab(),
                Action::make('printPos')
                    ->label(__('filament/pages/invoice_printing.actions.print_pos'))
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->url(fn (Order $record): string => route('invoices.print', ['order' => $record, 'format' => 'pos']))
                    ->openUrlInNewTab(),
            ])
                ->label(__('filament/pages/invoice_printing.actions.print'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->button()
                ->visible(fn (): bool => ! $this->getRecord()->trashed() && Gate::allows('viewAny', InvoicePrinting::class)),
            EditAction::make()
                ->hidden(fn (): bool => $this->getRecord()->trashed()),
            DeleteAction::make(),
        ];
    }
}
