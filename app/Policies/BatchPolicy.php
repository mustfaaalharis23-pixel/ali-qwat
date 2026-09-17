<?php

namespace App\Policies;

/**
 * Batches are read only: they are written with their purchase lines, so the only permission is batch.view.
 */
class BatchPolicy extends ModelPolicy
{
    /**
     * @return list<string>
     */
    public static function basicAbilities(): array
    {
        return ['view'];
    }
}
