<?php

namespace App\Filament\Pages;

use App\Filament\Lenses\InactiveItemsWithStockLens;
use App\Filament\Lenses\ItemsSoldAtALossLens;
use App\Filament\Lenses\ItemsWithALowMarginLens;
use App\Filament\Lenses\ItemsWithANegativeQuantityLens;
use App\Filament\Lenses\ItemsWithoutSalesLens;
use App\Filament\Lenses\ItemsWithoutSupplierLens;
use App\Filament\Widgets\LensCountCards;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The monitoring dashboard: the count cards of the highly sensitive lenses only. It comes right after the financial dashboard
 * in the sidebar, and only a user with the monitoring_dashboard.view permission, or a super user, can open it.
 */
class MonitoringDashboard extends Dashboard
{
    protected static string $routePath = 'monitoring-dashboard';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', static::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('filament/pages/monitoring_dashboard.title');
    }

    public function getTitle(): string
    {
        return __('filament/pages/monitoring_dashboard.title');
    }

    /**
     * The watched lenses, in the order their cards are listed. To watch a lens here, add its class.
     */
    public function getWidgets(): array
    {
        return [
            LensCountCards::make(['lenses' => [
                ItemsWithoutSupplierLens::class,
                InactiveItemsWithStockLens::class,
                ItemsWithANegativeQuantityLens::class,
                ItemsSoldAtALossLens::class,
                ItemsWithALowMarginLens::class,
                ItemsWithoutSalesLens::class,
            ]]),
        ];
    }
}
