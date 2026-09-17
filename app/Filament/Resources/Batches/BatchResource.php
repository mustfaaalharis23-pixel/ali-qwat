<?php

namespace App\Filament\Resources\Batches;

use App\Filament\Resources\Batches\Pages\ListBatches;
use App\Filament\Resources\Batches\Tables\BatchesTable;
use App\Models\Batch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Batches are read only and are not in the sidebar: they are seen inside an item, on its batches tab.
 * A batch is created, updated, and deleted with its purchase line, so it has no create, edit, or delete screen.
 */
class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('filament/resources/batch.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament/resources/batch.plural_label');
    }

    public static function table(Table $table): Table
    {
        return BatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatches::route('/'),
        ];
    }
}
