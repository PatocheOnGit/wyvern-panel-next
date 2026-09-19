<?php

namespace App\Providers\Filament;

use App\Enums\TablerIcon;
use App\Filament\Pages\Auth\EditProfile;
use App\Services\Helpers\PluginService;
use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Wyvern\Filament\WyvernPlugin;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = parent::panel($panel)
            ->id('app')
            ->default()
            ->breadcrumbs(false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationItems([
                NavigationItem::make(fn () => trans('server/dashboard.title'))
                    ->icon(TablerIcon::LayoutGrid)
                    ->url(fn () => Filament::getPanel('app')->getUrl())
                    ->isActiveWhen(fn () => request()->routeIs('filament.app.resources.*')),
                NavigationItem::make(fn () => trans('profile.title'))
                    ->icon(TablerIcon::UserCircle)
                    ->url(fn () => EditProfile::getUrl(panel: 'app'))
                    ->isActiveWhen(fn () => request()->routeIs('filament.app.auth.profile')),
                NavigationItem::make(fn () => trans('profile.admin'))
                    ->icon(TablerIcon::ArrowForward)
                    ->url(fn () => Filament::getPanel('admin')->getUrl())
                    ->visible(fn () => user()?->canAccessPanel(Filament::getPanel('admin')) ?? false),
            ])
            ->userMenuItems([
                Action::make('to_admin')
                    ->label(trans('profile.admin'))
                    ->url(fn () => Filament::getPanel('admin')->getUrl())
                    ->icon(TablerIcon::ArrowForward)
                    ->visible(fn () => user()?->canAccessPanel(Filament::getPanel('admin'))),
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->plugins([
                FilamentLogViewerPlugin::make()
                    ->authorize(false),
                WyvernPlugin::make(),
            ]);

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->loadPanelPlugins($panel);

        return $panel;
    }
}
