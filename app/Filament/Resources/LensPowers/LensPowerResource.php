<?php

namespace App\Filament\Resources\LensPowers;

use App\Filament\Resources\LensPowers\Pages\ManageLensPowers;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\LensPower;
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
 * The lens powers the purchased glasses come in, such as 1.50, from the lowest to the highest. It sits under items in the sidebar,
 * and a power is added or changed in a modal, since it is only a number.
 */
class LensPowerResource extends Resource
{
    protected static ?string $model = LensPower::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    public static function getModelLabel(): string
    {
        return __('filament/resources/lens_power.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/lens_power.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/lens_power.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.items_management');
    }

    public static function getNavigationParentItem(): ?string
    {
        return __('filament/resources/item.navigation_label');
    }

    /**
     * A power is a number with up to two decimals, and no two powers are the same.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->label(__('filament/resources/lens_power.fields.value'))
                    ->numeric()
                    ->rule('decimal:0,2')
                    ->required()
                    ->unique(LensPower::class, 'value', ignoreRecord: true),
            ]);
    }

    /**
     * A power that is on purchase lines cannot be deleted, so its delete action is hidden.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('invoiceItemGroups'))
            ->defaultSort('value')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('value')
                    ->label(__('filament/resources/lens_power.fields.value'))
                    ->sortable(),
                TextColumn::make('invoice_item_groups_count')
                    ->label(__('filament/resources/lens_power.fields.invoice_items_count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (LensPower $record): bool => $record->invoiceItemGroups()->withTrashed()->exists()),
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
            'index' => ManageLensPowers::route('/'),
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
