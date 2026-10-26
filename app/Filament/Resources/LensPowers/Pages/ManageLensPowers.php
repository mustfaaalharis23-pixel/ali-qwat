<?php

namespace App\Filament\Resources\LensPowers\Pages;

use App\Filament\Resources\LensPowers\LensPowerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLensPowers extends ManageRecords
{
    protected static string $resource = LensPowerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
