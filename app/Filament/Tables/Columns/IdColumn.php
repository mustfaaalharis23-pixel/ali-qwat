<?php

namespace App\Filament\Tables\Columns;

use Filament\Facades\Filament;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;

/**
 * The ID column used in every table: it shows the record's ID and opens the record's details (view) page when clicked.
 * The page belongs to the resource given with resource(); otherwise to the resource whose screen shows the table,
 * or, inside a tab, to the resource of the record's model.
 * When the record has no details page, or the user cannot view it, the ID is shown as plain text.
 */
class IdColumn extends TextColumn
{
    /**
     * @var class-string<\Filament\Resources\Resource>|null
     */
    protected ?string $detailsResource = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament/components/id_column.label'))
            ->sortable()
            ->color(fn (Model $record, HasTable $livewire): ?string => filled($this->detailsUrl($record, $livewire)) ? 'primary' : null)
            ->url(fn (Model $record, HasTable $livewire): ?string => $this->detailsUrl($record, $livewire));
    }

    /**
     * Opens the details page of the given resource. Needed when the model has more than one resource,
     * such as invoices: inside a tab, a purchase invoice must open in the purchase invoices resource, not the general invoices one.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public function resource(string $resource): static
    {
        $this->detailsResource = $resource;

        return $this;
    }

    /**
     * The URL of the record's details page, or null when there is none the user can open.
     */
    private function detailsUrl(Model $record, HasTable $livewire): ?string
    {
        $resource = $this->detailsResource
            ?? ($livewire instanceof ResourcePage ? $livewire::getResource() : Filament::getModelResource($record));

        if (blank($resource) || ! $resource::hasPage('view') || ! $resource::canView($record)) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $record]);
    }
}
