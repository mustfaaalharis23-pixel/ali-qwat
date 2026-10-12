<?php

namespace App\Filament\Resources\Containers;

use App\Filament\Resources\Audits\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Containers\Pages\CreateContainer;
use App\Filament\Resources\Containers\Pages\EditContainer;
use App\Filament\Resources\Containers\Pages\ListContainers;
use App\Filament\Resources\Containers\Pages\ViewContainer;
use App\Filament\Resources\Containers\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Containers\Schemas\ContainerForm;
use App\Filament\Resources\Containers\Tables\ContainersTable;
use App\Models\Container;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContainerResource extends Resource
{
    protected static ?string $model = Container::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    public static function getModelLabel(): string
    {
        return __('filament/resources/container.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/container.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/container.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.purchases');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return ContainerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContainersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InvoicesRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContainers::route('/'),
            'create' => CreateContainer::route('/create'),
            'view' => ViewContainer::route('/{record}'),
            'edit' => EditContainer::route('/{record}/edit'),
        ];
    }

    /**
     * The details page of the container, so its name opens it. Null when there is no container, or the user cannot open containers.
     */
    public static function viewUrlFor(?Container $container): ?string
    {
        if ($container === null || ! static::canView($container)) {
            return null;
        }

        return static::getUrl('view', ['record' => $container]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
