<?php

namespace App\Filament\Resources\SpecificDescriptions\Pages;

use App\Filament\Resources\SpecificDescriptions\SpecificDescriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpecificDescriptions extends ListRecords
{
    protected static string $resource = SpecificDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
