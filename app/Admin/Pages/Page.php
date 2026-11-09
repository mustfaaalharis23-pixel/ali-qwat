<?php

namespace App\Admin\Pages;

use Illuminate\Support\Facades\Gate;

/**
 * A screen of the admin application that is not a model, such as the financial dashboard, and that has its own Policy
 * (App\Policies\{Name}Policy). The class only names the screen: its Policy gives its permissions, which are stored
 * in user_permissions by the Policy's name (financial_dashboard.view), and the screen is opened by whoever passes viewAny.
 */
abstract class Page
{
    /**
     * The name of the screen, shown in the sidebar and as the group of its permissions.
     */
    abstract public static function label(): string;

    /**
     * Only a user with the screen's view permission, or a super user, can open it.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', static::class);
    }
}
