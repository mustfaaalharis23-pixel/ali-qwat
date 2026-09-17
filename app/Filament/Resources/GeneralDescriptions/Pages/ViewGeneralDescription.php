<?php

namespace App\Filament\Resources\GeneralDescriptions\Pages;

use App\Filament\Resources\GeneralDescriptions\GeneralDescriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGeneralDescription extends ViewRecord
{
    protected static string $resource = GeneralDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
