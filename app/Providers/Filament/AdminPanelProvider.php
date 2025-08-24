<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->sidebarCollapsibleOnDesktop()
            ->brandName(config('DELMAR International School'))
            ->brandLogo(asset('images/logo-delmar.png'))
            ->brandLogoHeight('40px')
            ->sidebarCollapsibleOnDesktop()

            // Ponemos esta página como inicio del panel:
            ->homeUrl(fn () => \App\Filament\Pages\RepositoryLanding::getUrl())

            // ⛔️ Sin Vite (no viteTheme)
            // ✅ Inyecta el CSS global desde /public con un hook
            ->renderHook('panels::styles', fn () => view('filament._custom-styles'))
            // Si en algún momento quieres JS global, descomenta la siguiente línea
            // y crea la vista resources/views/filament/_custom-scripts.blade.php
            //->renderHook('panels::scripts', fn () => view('filament._custom-scripts'))

            // Descubrimiento automático
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ]);
    }
}
