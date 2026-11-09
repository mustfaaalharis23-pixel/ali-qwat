<?php

namespace App\Http\Resources\Admin\System;

use App\Enums\Contracts\HasColor;
use App\Enums\Contracts\HasLabel;
use BackedEnum;

/**
 * An enum case as the browser shows it: its value, its label in the current language, and its badge color.
 */
class EnumValue
{
    /**
     * @return array{value: string|int, label: string, color: string|null}|null
     */
    public static function of(?BackedEnum $case): ?array
    {
        if ($case === null) {
            return null;
        }

        return [
            'value' => $case->value,
            'label' => $case instanceof HasLabel ? (string) $case->getLabel() : (string) $case->value,
            'color' => $case instanceof HasColor ? (is_string($color = $case->getColor()) ? $color : null) : null,
        ];
    }

    /**
     * Every case of an enum, for a select or a filter.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return list<array{value: string|int, label: string, color: string|null}>
     */
    public static function options(string $enum): array
    {
        return array_map(fn (BackedEnum $case): array => self::of($case), $enum::cases());
    }
}
