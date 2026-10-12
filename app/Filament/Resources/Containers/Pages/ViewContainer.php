<?php

namespace App\Filament\Resources\Containers\Pages;

use App\Filament\Resources\Containers\ContainerResource;
use App\Filament\Resources\Containers\Widgets\ContainerCbmMetric;
use App\Filament\Resources\Containers\Widgets\ContainerTransportCostMetric;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContainer extends ViewRecord
{
    protected static string $resource = ContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * The container's CBM and transport cost above its details.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ContainerCbmMetric::class,
            ContainerTransportCostMetric::class,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return ['md' => 2];
    }
}
