<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

/**
 * What a user is allowed to see and do inside the sales application. These are not model permissions:
 * every case here is one screen of the application, and they are saved in user_permissions like any other permission.
 *
 * Adding a screen later: add a case here, then its label in lang/en/permissions.php and lang/ar/permissions.php
 * under app. Nothing else in the system needs to change.
 */
enum AppPermission: string implements HasLabel
{
    case Orders = 'app.orders';

    case Checkout = 'app.checkout';

    case Receiving = 'app.receiving';

    public function getLabel(): string
    {
        return __('permissions.app.'.Str::after($this->value, '.'));
    }

    /**
     * Every application permission with its label, ready for the permission tree.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $permission): array => [$permission->value => $permission->getLabel()])
            ->all();
    }

    /**
     * The names of all the application permissions.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
