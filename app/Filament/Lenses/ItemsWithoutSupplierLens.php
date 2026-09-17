<?php

namespace App\Filament\Lenses;

use App\Filament\Tables\Columns\IdColumn;
use App\Filament\Widgets\Metrics\ItemWithoutSupplierCountMetric;
use App\Models\Item;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The items without a supplier, active or not, with their count card above the table. Deleted items are not shown.
 * Each row has an action to choose the item's supplier; once chosen, the item leaves the lens.
 */
class ItemsWithoutSupplierLens extends Lens
{
    public static function uriKey(): string
    {
        return 'items-without-supplier';
    }

    public static function label(): string
    {
        return __('filament/lenses.items_without_supplier.label');
    }

    public static function explanation(): string
    {
        return __('filament/lenses.items_without_supplier.explanation');
    }

    public static function guidance(): string
    {
        return __('filament/lenses.items_without_supplier.guidance');
    }

    public function query(): Builder
    {
        return Item::query()->active()->whereNull('supplier_id');
    }

    public function columns(): array
    {
        return [
            IdColumn::make('id'),
            ImageColumn::make('image')
                ->label(__('filament/resources/item.fields.image'))
                ->disk('public'),
            TextColumn::make('name')
                ->label(__('filament/resources/item.fields.name'))
                ->searchable(),
            TextColumn::make('sale_price')
                ->label(__('filament/resources/item.fields.sale_price'))
                ->numeric()
                ->sortable(),
            TextColumn::make('cost_price')
                ->label(__('filament/resources/item.fields.cost_price'))
                ->numeric()
                ->sortable(),
            IconColumn::make('is_active')
                ->label(__('filament/resources/item.fields.is_active'))
                ->boolean(),
        ];
    }

    /**
     * Choose the supplier of the item. Only a user who can edit items sees it.
     */
    public function recordActions(): array
    {
        return [
            Action::make('setSupplier')
                ->label(__('filament/resources/item.actions.set_supplier.label'))
                ->icon(Heroicon::OutlinedBuildingOffice)
                ->authorize('update')
                ->schema([
                    $this->supplierSelect(),
                ])
                ->action(function (Item $record, array $data, Action $action): void {
                    $record->update(['supplier_id' => $data['supplier_id']]);

                    $action->success();
                })
                ->successNotificationTitle(__('filament/resources/item.actions.set_supplier.saved')),
        ];
    }

    /**
     * Choose one supplier for all the selected items at once. Only a user who can edit items sees it.
     * Each item is saved on its own, so every change is audited.
     */
    public function toolbarActions(): array
    {
        return [
            BulkAction::make('setSupplierForSelected')
                ->label(__('filament/resources/item.actions.set_supplier.label'))
                ->icon(Heroicon::OutlinedBuildingOffice)
                ->visible(fn (): bool => Gate::allows('update', new Item))
                ->authorizeIndividualRecords('update')
                ->schema([
                    $this->supplierSelect(),
                ])
                ->action(function (Collection $records, array $data, BulkAction $action): void {
                    $records->each(fn (Item $record): bool => $record->update(['supplier_id' => $data['supplier_id']]));

                    $action->success();
                })
                ->deselectRecordsAfterCompletion()
                ->successNotificationTitle(__('filament/resources/item.actions.set_supplier.saved')),
        ];
    }

    /**
     * The supplier list of both actions, shown by the Arabic name like the item form.
     */
    private function supplierSelect(): Select
    {
        return Select::make('supplier_id')
            ->label(__('filament/resources/item.fields.supplier_id'))
            ->options(fn (): array => Supplier::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all())
            ->searchable()
            ->required();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ItemWithoutSupplierCountMetric::class,
        ];
    }

    /**
     * One column on small screens, two on medium screens, and four on large screens.
     *
     * @return array<string, int>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
