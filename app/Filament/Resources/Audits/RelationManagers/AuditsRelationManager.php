<?php

namespace App\Filament\Resources\Audits\RelationManagers;

use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Resources\Audits\Tables\AuditsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The audits of one record, shown as a tab on the record's page. Only users who can view audits see it.
 */
class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';

    protected static ?string $relatedResource = AuditResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/audit.plural_label');
    }

    public function table(Table $table): Table
    {
        return AuditsTable::configure($table, showSubject: false);
    }
}
