<?php

namespace App\Filament\Resources\Colors;

use App\Filament\Resources\Colors\Pages\ManageColors;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Color;
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
 * The colors the pieces in a purchased packet come in, such as red, by name. It sits under items in the sidebar,
 * and a color is added or changed in a modal, since it is only a name.
 */
class ColorResource extends Resource
{
    protected static ?string $model = Color::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    public static function getModelLabel(): string
    {
        return __('filament/resources/color.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/color.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/color.navigation_label');
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
     * A color is a name, and no two colors have the same name.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament/resources/color.fields.name'))
                    ->maxLength(255)
                    ->required()
                    ->unique(Color::class, 'name', ignoreRecord: true),
            ]);
    }

    /**
     * A color that is in the packets of purchase lines cannot be deleted, so its delete action is hidden.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('invoiceItemColors'))
            ->defaultSort('name')
            ->columns([
                IdColumn::make('id'),
                TextColumn::make('name')
                    ->label(__('filament/resources/color.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_item_colors_count')
                    ->label(__('filament/resources/color.fields.invoice_item_colors_count'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Color $record): bool => $record->invoiceItemColors()->withTrashed()->exists()),
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
            'index' => ManageColors::route('/'),
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
