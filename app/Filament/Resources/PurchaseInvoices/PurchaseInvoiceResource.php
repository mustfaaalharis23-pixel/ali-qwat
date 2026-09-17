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
        return __('filament/navigation.groups.accounting');
    }

    public static function getNavigationSort(): int
    {
        return 4;
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
