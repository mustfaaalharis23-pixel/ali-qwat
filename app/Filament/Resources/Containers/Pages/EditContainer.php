<?php

namespace App\Filament\Resources\Containers\Pages;

use App\Filament\Resources\Containers\ContainerResource;
use App\Models\Container;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditContainer extends EditRecord
{
    protected static string $resource = ContainerResource::class;

    /**
     * A container that has invoices cannot be deleted, so its delete actions are hidden.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (Container $record): bool => $record->invoices()->exists()),
            ForceDeleteAction::make()
                ->hidden(fn (Container $record): bool => $record->invoices()->withTrashed()->exists()),
            RestoreAction::make(),
        ];
    }
}
