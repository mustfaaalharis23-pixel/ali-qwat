<?php

namespace App\Providers;

use App\Models\Audit;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * How a date is written across the panel.
     */
    private const DATE_FORMAT = 'Y/m/d';

    /**
     * How a date and time is written across the panel.
     */
    private const DATE_TIME_FORMAT = 'Y/m/d H:i';

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
        $this->registerSuperOnlyMacros();
        $this->registerDateFormats();

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
     * Every date in the panel is shown as year/month/day, and every date and time as year/month/day hour:minute,
     * instead of the month name Filament writes by default, so tables, details pages, and the date field all read the same.
     */
    private function registerDateFormats(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));
    }

    /**
     * Adds ->superOnly() to Filament form components (fields and sections), table columns, and actions, so they are shown only to super users.
     */
    private function registerSuperOnlyMacros(): void
    {
        $superOnly = function (): static {
            /** @var SchemaComponent|Column|Action $this */
            return $this->visible(fn (): bool => auth()->user()?->is_super === true);
        };

        SchemaComponent::macro('superOnly', $superOnly);
        Column::macro('superOnly', $superOnly);
        Action::macro('superOnly', $superOnly);
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
