<?php

namespace App\Filament\Resources\SpecificDescriptions;

use App\Filament\Resources\Audits\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\SpecificDescriptions\Pages\CreateSpecificDescription;
use App\Filament\Resources\SpecificDescriptions\Pages\EditSpecificDescription;
use App\Filament\Resources\SpecificDescriptions\Pages\ListSpecificDescriptions;
use App\Filament\Resources\SpecificDescriptions\Pages\ViewSpecificDescription;
use App\Filament\Resources\SpecificDescriptions\Schemas\SpecificDescriptionForm;
use App\Filament\Resources\SpecificDescriptions\Tables\SpecificDescriptionsTable;
use App\Models\SpecificDescription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SpecificDescriptionResource extends Resource
{
    protected static ?string $model = SpecificDescription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('filament/resources/specific_description.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/specific_description.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/specific_description.navigation_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.accounting');
    }

    /**
     * It sits under general invoices in the sidebar, since it only sorts their lines, and shows while they are open.
     */
    public static function getNavigationParentItem(): ?string
    {
        return __('filament/resources/general_invoice.navigation_label');
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return SpecificDescriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpecificDescriptionsTable::configure($table);
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
            'index' => ListSpecificDescriptions::route('/'),
            'create' => CreateSpecificDescription::route('/create'),
            'view' => ViewSpecificDescription::route('/{record}'),
            'edit' => EditSpecificDescription::route('/{record}/edit'),
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
