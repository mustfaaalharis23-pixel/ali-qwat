<?php

namespace App\Filament\Resources\GeneralDescriptions\Pages;

use App\Filament\Resources\GeneralDescriptions\GeneralDescriptionResource;
use App\Models\GeneralDescription;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralDescription extends EditRecord
{
    protected static string $resource = GeneralDescriptionResource::class;

    /**
     * The purchases description cannot be deleted from the screen, because every item's description is under it.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (GeneralDescription $record): bool => $record->is_purchases_description),
            ForceDeleteAction::make()
                ->hidden(fn (GeneralDescription $record): bool => $record->is_purchases_description),
            RestoreAction::make(),
        ];
    }
}
