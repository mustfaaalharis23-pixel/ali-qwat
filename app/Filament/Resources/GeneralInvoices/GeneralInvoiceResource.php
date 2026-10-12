<?php

namespace App\Filament\Resources\GeneralInvoices;

use App\Filament\Resources\Audits\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\GeneralInvoices\Pages\CreateGeneralInvoice;
use App\Filament\Resources\GeneralInvoices\Pages\EditGeneralInvoice;
use App\Filament\Resources\GeneralInvoices\Pages\ListGeneralInvoices;
use App\Filament\Resources\GeneralInvoices\Pages\ViewGeneralInvoice;
use App\Filament\Resources\GeneralInvoices\Schemas\GeneralInvoiceForm;
use App\Filament\Resources\GeneralInvoices\Tables\GeneralInvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The general invoices: invoices whose general description is not the purchases description.
 */
class GeneralInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'general-invoices';

    public static function getModelLabel(): string
    {
        return __('filament/resources/general_invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/general_invoice.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/general_invoice.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.accounting');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return GeneralInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneralInvoices::route('/'),
            'create' => CreateGeneralInvoice::route('/create'),
            'view' => ViewGeneralInvoice::route('/{record}'),
            'edit' => EditGeneralInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('generalDescription', fn (Builder $query): Builder => $query->where('is_purchases_description', false));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
