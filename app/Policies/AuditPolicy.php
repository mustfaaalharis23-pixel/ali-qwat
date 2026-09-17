<?php

namespace App\Policies;

/**
 * Audits are read only: the only permission is audit.view.
 */
class AuditPolicy extends ModelPolicy
{
    /**
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view'];
    }
}
