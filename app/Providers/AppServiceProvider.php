<?php

namespace App\Providers;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The language numbers are written in across the system (what Laravel's Number helper formats: the screens, the printed
     * invoices, the metrics), so they always have English digits (0-9), whatever the language.
     */
    private const NUMBER_LOCALE = 'en';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Number::useLocale(self::NUMBER_LOCALE);

        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if (! $user->is_super) {
                return null;
            }

            if ($this->isForceDeletingUsers($ability, $arguments) || $this->isChangingAudits($ability, $arguments)) {
                return false;
            }

            return true;
        });

        Gate::define('viewLens', fn (User $user, string $lens): bool => $user->hasPermission($lens::permission()));
    }

    /**
     * Users are never force deleted, not even by a super user.
     *
     * @param  array<int, mixed>  $arguments
     */
    private function isForceDeletingUsers(string $ability, array $arguments): bool
    {
        $target = $arguments[0] ?? null;

        return in_array($ability, ['forceDelete', 'forceDeleteAny'], true)
            && ($target instanceof User || $target === User::class);
    }

    /**
     * Audits are read only, not even a super user can change them.
     *
     * @param  array<int, mixed>  $arguments
     */
    private function isChangingAudits(string $ability, array $arguments): bool
    {
        $target = $arguments[0] ?? null;

        return ! in_array($ability, ['viewAny', 'view'], true)
            && ($target instanceof Audit || $target === Audit::class);
    }
}
