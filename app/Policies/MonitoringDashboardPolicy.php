<?php

namespace App\Policies;

/**
 * The monitoring dashboard is a page that is only viewed: the only permission is monitoring_dashboard.view.
 */
class MonitoringDashboardPolicy extends ModelPolicy
{
    /**
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view'];
    }
}
