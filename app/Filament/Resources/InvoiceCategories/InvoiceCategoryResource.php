<?php

namespace App\Filament\Resources\InvoiceCategories;

use App\Filament\Resources\InvoiceCategories\Pages\ManageInvoiceCategories;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\InvoiceCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The categories of purchase invoices, such as frames and lenses. It sits under purchase invoices in the sidebar,
 * and a category is added or renamed in a modal, since it is only a name.
 */
class InvoiceCategoryResource extends Resource
{
    protected static ?string $model = InvoiceCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function getModelLabel(): string
    {
        return __('filament/resources/invoice_category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/invoice_category.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/invoice_category.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.purchases');
    }

    public static function getNavigationParentItem(): ?string
    {
        return __('filament/resources/purchase_invoice.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::nameInput(),
            ]);
    }

    /**
     * The name of a category, unique among the categories. The purchase invoice form reuses it to add a category in place.
     */
    public static function nameInput(): TextInput
    {
        return TextInput::make('name')
            ->label(__('filament/resources/invoice_category.fields.name'))
            ->required()
            ->maxLength(255)
            ->unique(InvoiceCategory::class, 'name', ignoreRecord: true);
    }

    /**
     * A category that has invoices cannot be deleted, so its delete action is hidden.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('invoices'))
            ->defaultSort('id', 'desc')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/invoice_category.fields.name'))
                    ->searchable(),
                TextColumn::make('invoices_count')
                    ->label(__('filament/resources/invoice_category.fields.invoices_count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (InvoiceCategory $record): bool => $record->invoices()->withTrashed()->exists()),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInvoiceCategories::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
