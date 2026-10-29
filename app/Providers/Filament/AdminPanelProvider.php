<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\CustomerPayments\OrderRemindersTable;
use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverPages(in: app_path('Filament/Lenses'), for: 'App\Filament\Lenses')
            ->discoverPages(in: app_path('Filament/Reports'), for: 'App\Filament\Reports')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups($this->navigationGroups())
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                OrderRemindersTable::class,
            ])
            ->userMenuItems($this->localeSwitchActions())
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('filament.locale-switch'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([
                SetLocale::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * The sidebar groups in the order they show: the daily work first (selling, buying, the items), then the money,
     * the reports, and the system's own screens last. The lenses start folded, since they are looked at now and then.
     * Each label is read when the sidebar is drawn, so it follows the user's language.
     *
     * @return array<string, NavigationGroup>
     */
    private function navigationGroups(): array
    {
        return collect(['sales', 'purchases', 'items_management', 'lenses', 'accounting', 'reports', 'system_administration'])
            ->mapWithKeys(fn (string $group): array => [
                $group => NavigationGroup::make()
                    ->label(fn (): string => __("filament/navigation.groups.{$group}"))
                    ->collapsed($group === 'lenses'),
            ])
            ->all();
    }

    /**
     * A user menu link for each supported language, named in its own language. The current language is hidden,
     * which is read when the menu is drawn, so the links follow the user's language.
     *
     * @return array<int, Action>
     */
    private function localeSwitchActions(): array
    {
        return collect(config('app.locales'))
            ->map(fn (string $name, string $locale): Action => Action::make("switchLocale_{$locale}")
                ->label($name)
                ->icon(Heroicon::Language)
                ->url(fn (): string => route('locale.switch', $locale))
                ->hidden(fn (): bool => app()->getLocale() === $locale))
            ->values()
            ->all();
    }
}
