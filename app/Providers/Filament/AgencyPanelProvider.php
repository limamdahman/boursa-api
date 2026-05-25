<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Agency\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgencyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agency')
            ->path('agence')
            ->login()
            ->brandName('Boursa Pro')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Tableau de bord'),
                NavigationGroup::make('Mes annonces'),
                NavigationGroup::make('Clients'),
                NavigationGroup::make('Mon agence'),
            ])
            ->discoverResources(in: app_path('Filament/Agency/Resources'), for: 'App\\Filament\\Agency\\Resources')
            ->discoverPages(in: app_path('Filament/Agency/Pages'), for: 'App\\Filament\\Agency\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Agency/Widgets'), for: 'App\\Filament\\Agency\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
