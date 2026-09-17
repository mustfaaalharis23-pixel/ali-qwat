<?php

namespace App\Filament\Resources\GeneralDescriptions\Pages;

use App\Filament\Resources\GeneralDescriptions\GeneralDescriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneralDescriptions extends ListRecords
{
    protected static string $resource = GeneralDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
