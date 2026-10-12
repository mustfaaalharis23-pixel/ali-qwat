<?php

namespace App\Filament\Resources\Containers\Pages;

use App\Filament\Resources\Containers\ContainerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContainers extends ListRecords
{
    protected static string $resource = ContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
