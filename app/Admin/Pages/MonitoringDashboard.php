<?php

namespace App\Admin\Pages;

/**
 * The monitoring dashboard: the count cards of the highly sensitive lenses. Its permission is monitoring_dashboard.view.
 */
class MonitoringDashboard extends Page
{
    public static function label(): string
    {
        return __('filament/pages/monitoring_dashboard.title');
    }
}
