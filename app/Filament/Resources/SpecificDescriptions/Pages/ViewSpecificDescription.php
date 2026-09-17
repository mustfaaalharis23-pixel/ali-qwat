<?php

namespace App\Filament\Resources\SpecificDescriptions\Pages;

use App\Filament\Resources\SpecificDescriptions\SpecificDescriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSpecificDescription extends ViewRecord
{
    protected static string $resource = SpecificDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
