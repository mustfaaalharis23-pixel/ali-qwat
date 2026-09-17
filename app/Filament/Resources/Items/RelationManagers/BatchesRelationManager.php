<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Filament\Resources\Batches\BatchResource;
use App\Filament\Resources\Batches\Tables\BatchesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The stock batches of one item, shown as a tab on the item's page. They are read only.
 */
class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    protected static ?string $relatedResource = BatchResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament/resources/batch.plural_label');
    }

    public function table(Table $table): Table
    {
        return BatchesTable::configure($table, showItem: false);
    }
}
