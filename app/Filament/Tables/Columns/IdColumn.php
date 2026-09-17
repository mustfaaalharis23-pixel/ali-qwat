<?php

namespace App\Filament\Tables\Columns;

use Filament\Facades\Filament;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;

/**
 * The ID column used in every table: it shows the record's ID and opens the record's details (view) page when clicked.
 * The page belongs to the resource whose screen shows the table, or, inside a tab, to the resource of the record's model.
 * When the record has no details page, or the user cannot view it, the ID is shown as plain text.
 */
class IdColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament/components/id_column.label'))
            ->sortable()
            ->color(fn (Model $record, HasTable $livewire): ?string => filled(self::detailsUrl($record, $livewire)) ? 'primary' : null)
            ->url(fn (Model $record, HasTable $livewire): ?string => self::detailsUrl($record, $livewire));
    }

    /**
     * The URL of the record's details page, or null when there is none the user can open.
     */
    private static function detailsUrl(Model $record, HasTable $livewire): ?string
    {
        $resource = $livewire instanceof ResourcePage ? $livewire::getResource() : Filament::getModelResource($record);

        if (blank($resource) || ! $resource::hasPage('view') || ! $resource::canView($record)) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $record]);
    }
}
