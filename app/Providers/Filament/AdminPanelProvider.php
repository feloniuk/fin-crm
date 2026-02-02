<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\CompanyLimitsWidget;
use App\Filament\Widgets\RecentOrdersWidget;
use App\Filament\Pages\CompanyLimitsSettings;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->brandName('CRM Система')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->renderHook(
                'panels::head.end',
                fn () => '<style>
                    /* Fix horizontal scroll in tables on desktop */
                    @media (min-width: 1024px) {
                        .fi-ta-table { width: 100% !important; table-layout: auto !important; }
                        .fi-ta-cell, .fi-ta-header-cell { white-space: normal !important; word-break: break-word !important; }
                        .fi-ta-actions-cell { white-space: nowrap !important; }
                    }
                    .fi-main { min-width: 0 !important; }

                    /* Sticky table toolbar */
                    .fi-ta-header-toolbar {
                        position: sticky;
                        top: 0;
                        z-index: 20;
                        background-color: white;
                        border-bottom: 1px solid rgb(229 231 235);
                    }
                    .dark .fi-ta-header-toolbar {
                        background-color: rgb(17 24 39);
                        border-bottom-color: rgb(55 65 81);
                    }

                    /* Sticky table header */
                    .fi-ta-header {
                        position: sticky;
                        top: 57px;
                        z-index: 10;
                        background-color: rgb(249 250 251);
                    }
                    .dark .fi-ta-header {
                        background-color: rgb(31 41 55);
                    }
                </style>'
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                CompanyLimitsSettings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                StatsOverviewWidget::class,
                RecentOrdersWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
