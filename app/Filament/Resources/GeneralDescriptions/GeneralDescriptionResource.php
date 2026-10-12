<?php

namespace App\Filament\Resources\GeneralDescriptions;

use App\Filament\Resources\Audits\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\GeneralDescriptions\Pages\CreateGeneralDescription;
use App\Filament\Resources\GeneralDescriptions\Pages\EditGeneralDescription;
use App\Filament\Resources\GeneralDescriptions\Pages\ListGeneralDescriptions;
use App\Filament\Resources\GeneralDescriptions\Pages\ViewGeneralDescription;
use App\Filament\Resources\GeneralDescriptions\RelationManagers\SpecificDescriptionsRelationManager;
use App\Filament\Resources\GeneralDescriptions\Schemas\GeneralDescriptionForm;
use App\Filament\Resources\GeneralDescriptions\Tables\GeneralDescriptionsTable;
use App\Models\GeneralDescription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GeneralDescriptionResource extends Resource
{
    protected static ?string $model = GeneralDescription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('filament/resources/general_description.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/general_description.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/resources/general_description.navigation_label');
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
        return 1;
    }

    public static function form(Schema $schema): Schema
    {
        return GeneralDescriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralDescriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SpecificDescriptionsRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneralDescriptions::route('/'),
            'create' => CreateGeneralDescription::route('/create'),
            'view' => ViewGeneralDescription::route('/{record}'),
            'edit' => EditGeneralDescription::route('/{record}/edit'),
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
