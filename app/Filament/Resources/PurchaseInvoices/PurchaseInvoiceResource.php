<?php

namespace App\Filament\Resources\PurchaseInvoices;

use App\Filament\Resources\Audits\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Resources\PurchaseInvoices\Pages\ViewPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\RelationManagers\PayoffsRelationManager;
use App\Filament\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Filament\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceInfolist;
use App\Filament\Resources\PurchaseInvoices\Tables\PurchaseInvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The purchase invoices: invoices whose general description is the purchases description.
 */
class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'purchase-invoices';

    public static function getModelLabel(): string
    {
        return __('filament/resources/purchase_invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/purchase_invoice.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/purchase_invoice.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.purchases');
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PayoffsRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'view' => ViewPurchaseInvoice::route('/{record}'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * A small chip under the invoice's name telling a split apart: a part says which invoice it was split from,
     * and an original says which parts were split off it. Null for an invoice that was never split.
     * It reads the invoice's split parts, so a list should load them with the invoices.
     */
    public static function splitFacts(Invoice $invoice): ?View
    {
        if ($invoice->split_from_invoice_id !== null) {
            $fact = [
                'text' => __('filament/resources/purchase_invoice.split.part', ['number' => $invoice->split_from_invoice_id]),
                'tooltip' => __('filament/resources/purchase_invoice.split.part_hint', ['number' => $invoice->split_from_invoice_id]),
                'color' => 'warning',
                'icon' => Heroicon::OutlinedScissors,
            ];
        } elseif ($invoice->splitParts->isNotEmpty()) {
            $fact = [
                'text' => __('filament/resources/purchase_invoice.split.original', [
                    'numbers' => $invoice->splitParts->pluck('id')->map(fn (int $id): string => "#{$id}")->implode(' '),
                ]),
                'tooltip' => __('filament/resources/purchase_invoice.split.original_hint'),
                'color' => 'gray',
                'icon' => Heroicon::OutlinedDocumentDuplicate,
            ];
        } else {
            return null;
        }

        return view('filament.forms.components.line-facts', ['facts' => [$fact]]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('generalDescription', fn (Builder $query): Builder => $query->where('is_purchases_description', true));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
