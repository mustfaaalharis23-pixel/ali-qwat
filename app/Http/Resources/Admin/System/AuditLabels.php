<?php

namespace App\Http\Resources\Admin\System;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * How an audit is named in the current language: its event, the model it is about, and the fields it changed.
 */
final class AuditLabels
{
    /**
     * The events an audit can have.
     *
     * @var list<string>
     */
    public const EVENTS = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    /**
     * The label of an audit event in the current language, such as Created.
     */
    public static function eventLabel(?string $event): string
    {
        $key = "filament/resources/audit.events.{$event}";

        return Lang::has($key) ? __($key) : Str::headline((string) $event);
    }

    /**
     * The badge color of an audit event.
     */
    public static function eventColor(?string $event): string
    {
        return match ($event) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted', 'forceDeleted' => 'danger',
            'restored' => 'info',
            default => 'gray',
        };
    }

    /**
     * The label of the audited model in the current language, such as Item.
     */
    public static function subjectTypeLabel(?string $subjectType): string
    {
        $model = class_basename((string) $subjectType);
        $key = 'filament/resources/'.Str::snake($model).'.label';

        return Lang::has($key) ? __($key) : Str::headline($model);
    }

    /**
     * The label of a field of the audited model in the current language: the model's own field label first, then the common audit field labels.
     */
    public static function attributeLabel(?string $subjectType, string $attribute): string
    {
        $modelKey = 'filament/resources/'.Str::snake(class_basename((string) $subjectType)).".fields.{$attribute}";
        $commonKey = "filament/resources/audit.attributes.{$attribute}";

        return match (true) {
            Lang::has($modelKey) => __($modelKey),
            Lang::has($commonKey) => __($commonKey),
            default => Str::headline($attribute),
        };
    }
}
