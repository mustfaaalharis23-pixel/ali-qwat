<?php

namespace App\Filament\Resources\SpecificDescriptions\Pages;

use App\Filament\Resources\SpecificDescriptions\SpecificDescriptionResource;
use App\Models\SpecificDescription;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSpecificDescription extends EditRecord
{
    protected static string $resource = SpecificDescriptionResource::class;

    /**
     * An item description cannot be deleted from the screen, because it follows its item.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (SpecificDescription $record): bool => $record->item_id !== null),
            ForceDeleteAction::make()
                ->hidden(fn (SpecificDescription $record): bool => $record->item_id !== null),
            RestoreAction::make(),
        ];
    }
}
