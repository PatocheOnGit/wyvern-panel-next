<?php

namespace App\Providers\Filament;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Http\Middleware\Activity\ServerSubject;
use App\Models\Server;
use App\Services\Helpers\PluginService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Wyvern\Filament\WyvernPlugin;

class ServerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = parent::panel($panel)
            ->id('server')
            ->path('server')
            ->homeUrl(fn () => Filament::getPanel('app')->getUrl())
            ->tenant(Server::class, 'uuid_short')
            ->userMenuItems([
                Action::make('to_serverList')
                    ->label(trans('profile.server_list'))
                    ->icon(TablerIcon::BrandDocker)
                    ->url(fn () => ListServers::getUrl(panel: 'app')),
                Action::make('to_admin')
                    ->label(trans('profile.admin'))
                    ->icon(TablerIcon::ArrowForward)
                    ->url(fn () => Filament::getPanel('admin')->getUrl())
                    ->visible(fn () => user()?->canAccessPanel(Filament::getPanel('admin'))),
            ])
            // Ordered the way the sidebar reads. Console and Files stay ungrouped, so
            // Filament renders them above every group: they are where the time goes.
            ->navigationGroups([
                NavigationGroup::make(fn () => trans('wyvern.navigation.game')),
                NavigationGroup::make(fn () => trans('wyvern.navigation.software')),
                NavigationGroup::make(fn () => trans('wyvern.navigation.data')),
                NavigationGroup::make(fn () => trans('wyvern.navigation.automation')),
                NavigationGroup::make(fn () => trans('wyvern.navigation.access')),
                NavigationGroup::make(fn () => trans('wyvern.navigation.configuration')),
            ])
            ->navigationItems([
                NavigationItem::make(trans('server/console.open_in_admin'))
                    ->url(fn () => EditServer::getUrl(['record' => Filament::getTenant()], panel: 'admin'))
                    ->visible(fn () => user()?->canAccessPanel(Filament::getPanel('admin')) && user()->can('view server', Filament::getTenant()))
                    ->icon(TablerIcon::ArrowBack)
                    // Ungrouped it sorted to the end of a flat list; with groups, ungrouped
                    // means top, which is the last place an admin-only escape hatch belongs.
                    ->group(fn () => trans('wyvern.navigation.configuration'))
                    ->sort(3),
            ])
            ->discoverResources(in: app_path('Filament/Server/Resources'), for: 'App\\Filament\\Server\\Resources')
            ->discoverPages(in: app_path('Filament/Server/Pages'), for: 'App\\Filament\\Server\\Pages')
            ->discoverWidgets(in: app_path('Filament/Server/Widgets'), for: 'App\\Filament\\Server\\Widgets')
            ->tenantMiddleware([
                ServerSubject::class,
            ])
            ->plugins([
                WyvernPlugin::make(),
            ]);

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->loadPanelPlugins($panel);

        return $panel;
    }
}
