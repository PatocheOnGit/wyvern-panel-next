<?php

namespace Wyvern;

use App\Enums\CustomizationKey;
use App\Enums\HeaderActionPosition;
use App\Enums\HeaderWidgetPosition;
use App\Enums\TablerIcon;
use App\Events\Server\Installed;
use App\Extensions\Features\FeatureService;
use App\Facades\Activity;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Filament\Server\Pages\ServerFormPage;
use App\Filament\Server\Resources\Activities\Pages\ListActivities;
use App\Filament\Server\Resources\Allocations\Pages\ListAllocations;
use App\Filament\Server\Resources\Backups\Pages\ListBackups;
use App\Filament\Server\Resources\Databases\Pages\ListDatabases;
use App\Filament\Server\Resources\Files\Pages\ListFiles;
use App\Filament\Server\Resources\Schedules\Pages\ListSchedules;
use App\Filament\Server\Resources\Subusers\Pages\ListSubusers;
use App\Filament\Server\Resources\Webhooks\Pages\ListWebhooks;
use App\Models\Egg;
use App\Models\Server;
use App\Models\Subuser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Wyvern\Console\Commands\CheckContentLibrary;
use Wyvern\Console\Commands\CheckMinecraftCatalogue;
use Wyvern\Console\Commands\InstallModpack;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Filament\App\Pages\DatabaseAccess;
use Wyvern\Filament\Widgets\ShortcutsWidget;
use Wyvern\Http\AuthorizePhpMyAdmin;
use Wyvern\Http\ClaimPhpMyAdminSignon;
use Wyvern\Http\PlayerHead;
use Wyvern\Minecraft\Features\ConsoleFixes;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\InstallRecords;
use Wyvern\Navigation\MobileTabs;

/**
 * Everything Wyvern adds to the panel that is not a panel configuration call.
 *
 * Keeping it here rather than in App\Providers means the diff against upstream stays
 * one line in bootstrap/providers.php, and a rebase never has to reconcile our render
 * hooks with theirs.
 */
class WyvernServiceProvider extends ServiceProvider
{
    /**
     * The comfortable density, as two token values.
     *
     * Server-rendered rather than stamped onto <html> by a script, because a script that
     * sets an attribute after first paint shows the compact layout and then jumps. The
     * whole preference is two numbers, so there is nothing to load and nothing to flash.
     */
    private static function densityOverride(): string
    {
        if (user()?->getCustomization(CustomizationKey::Density) !== 'comfortable') {
            return '';
        }

        return '<style>:root{--wy-row-h:3rem;--wy-pad:1rem;--wy-gap:1rem}</style>';
    }

    /**
     * Wyvern's own web routes.
     *
     * An invokable controller rather than a closure: `route:cache` refuses to serialise a
     * closure, and a cached route table is the normal state of an installed panel, so a
     * closure here would break the very command the installer runs.
     *
     * `web` and nothing else. The route has to answer a guest with 403 instead of being
     * redirected to the login page — see the controller for why that distinction decides
     * whether phpMyAdmin shows a login prompt or a 500.
     */
    private function registerRoutes(): void
    {
        Route::middleware('web')
            ->get('/wyvern/internal/pma-authorize', AuthorizePhpMyAdmin::class)
            ->name('wyvern.internal.pma-authorize');

        // 'web' on this one, because the session is the whole point: phpMyAdmin's signon
        // script forwards the visitor's cookies, and this route answers with whatever that
        // visitor chose in the picker. Without session middleware there would be no way to
        // tell one visitor's selection from another's.
        Route::middleware('web')
            ->get('/wyvern/internal/pma-claim', ClaimPhpMyAdminSignon::class)
            ->name('wyvern.internal.pma-claim');

        Route::middleware(['web', 'throttle:240,1'])
            ->get('/wyvern/heads/{name}', PlayerHead::class)
            ->where('name', '\.?[A-Za-z0-9_]{1,16}')
            ->name('wyvern.heads');
    }

    /**
     * The way from a database to phpMyAdmin.
     *
     * On the page where someone is already looking at a database, rather than as a
     * top-level menu entry: arriving at the picker from here means the database is chosen
     * before the page loads, and the only question left is its password.
     */
    private function registerDatabaseShortcut(): void
    {
        if (!config('wyvern.phpmyadmin.enabled')) {
            return;
        }

        ListDatabases::registerCustomHeaderActions(
            HeaderActionPosition::After,
            Action::make('open_phpmyadmin')
                ->label(trans('wyvern.database_access.actions.open'))
                ->icon(TablerIcon::Database)
                ->color('gray')
                ->url(fn () => DatabaseAccess::getUrl(panel: 'app'))
                ->visible(fn () => user() !== null),
        );
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerDatabaseShortcut();

        // Checked as minecraft.players and minecraft.properties.
        Subuser::registerCustomPermissions('minecraft', ['players', 'properties'], 'wyvern.permissions', TablerIcon::Cube);

        $this->callAfterResolving(FeatureService::class, function (FeatureService $features, $app) {
            foreach (ConsoleFixes::all($app->make(MinecraftFiles::class)) as $schema) {
                $features->register($schema);
            }
        });

        // Filament serves its own panel stylesheet, and Pelican injects app.css through
        // STYLES_BEFORE. Ours has to land after both to reshape their surfaces, so it
        // goes on the other hook rather than relying on Vite's emission order.
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn () => Blade::render("@vite(['resources/css/wyvern-theme.css'])") . self::densityOverride(),
        );

        // The command palette, on every panel. BODY_END so it is a sibling of the page
        // rather than inside its scroll container, which is what lets it sit over
        // everything without a stacking-context fight.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => Filament::auth()->check()
                ? Blade::render("@include('wyvern.shell.command-palette')")
                : '',
        );

        // Keyboard shortcuts and the help sheet that teaches them. Same hook and same
        // reasoning as the palette.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => Filament::auth()->check()
                ? Blade::render("@include('wyvern.shell.shortcuts')")
                : '',
        );

        // The trigger goes in the topbar of the two panels that have no search field of
        // their own. The admin panel already carries the global search modal there, and
        // two search-shaped controls side by side would be a worse answer than one —
        // the shortcut still works there.
        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_END,
            fn () => Filament::auth()->check()
                && in_array(Filament::getCurrentPanel()?->getId(), ['app', 'server'], true)
                    ? Blade::render("@include('wyvern.shell.command-trigger')")
                    : '',
        );

        // A phone reaches the panel's busiest destinations in one tap instead of two.
        // BODY_END rather than a layout override: it renders after the page, so the bar
        // paints over content rather than inside a scroll container, and no upstream view
        // is touched to get it there.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => MobileTabs::shouldRender()
                ? Blade::render("@include('wyvern.shell.mobile-tabs')")
                : '',
        );

        // The host's own links, above the server cards. This is the extension point
        // Pelican already provides, so no upstream page is modified to get them there.
        ListServers::registerCustomHeaderWidgets(
            HeaderWidgetPosition::Before,
            ShortcutsWidget::class,
        );

        // Power controls on every page of a server, not just the console.
        //
        // CanCustomizeHeaderActions is the seam Pelican already provides, and the trait
        // declares its registry as a static — which is per-using-class, so each page has
        // to be named. Registering on ServerFormPage covers Settings, Startup and Mounts
        // together, because a static declared in a parent is shared with its subclasses.
        //
        // Console is deliberately absent: it already shows Start / Restart / Stop as
        // explicit buttons, and a Power menu beside them would be the same controls twice.
        $powerPages = [
            ListFiles::class,
            ListBackups::class,
            ListDatabases::class,
            ListSchedules::class,
            ListSubusers::class,
            ListAllocations::class,
            ListActivities::class,
            ListWebhooks::class,
            ServerFormPage::class,
        ];

        foreach ($powerPages as $page) {
            $page::registerCustomHeaderActions(HeaderActionPosition::Before, PowerActions::group());
        }

        // Upstream logs neither deletion. Model events, so every path is covered.
        // servers.egg_id is RESTRICT: an egg with servers cannot be deleted at all.
        Server::deleted(function (Server $server) {
            Activity::event('server:delete')
                ->property('name', $server->name)
                ->property('uuid', $server->uuid)
                ->log();
        });

        Egg::deleted(function (Egg $egg) {
            Activity::event('egg:delete')
                ->property('name', $egg->name)
                ->log();
        });

        // A reinstall rewrites .wyvern/install.json.
        Event::listen(Installed::class, fn (Installed $event) => InstallRecords::forget($event->server));

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckContentLibrary::class,
                CheckMinecraftCatalogue::class,
                InstallModpack::class,
            ]);
        }
    }
}
