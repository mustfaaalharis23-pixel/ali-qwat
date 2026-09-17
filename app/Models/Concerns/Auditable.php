<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records an audit for every change on the model with a full copy of the record.
 * Hidden attributes, such as password and remember_token, are never recorded.
 */
trait Auditable
{
    use LogsActivity {
        LogsActivity::eventsToBeRecorded as logsActivityEventsToBeRecorded;
    }

    /**
     * Log all the attributes of the record, except the hidden ones.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept($this->getHidden());
    }

    /**
     * The created, updated, deleted and restored events, plus forceDeleted for models that soft delete.
     *
     * @return Collection<int, string>
     */
    protected static function eventsToBeRecorded(): Collection
    {
        $events = static::logsActivityEventsToBeRecorded();

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            $events->push('forceDeleted');
        }

        return $events;
    }
}
