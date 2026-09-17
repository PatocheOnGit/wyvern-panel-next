<?php

namespace Wyvern;

use App\Enums\HeaderWidgetPosition;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Wyvern\Console\Commands\CheckContentLibrary;
use Wyvern\Console\Commands\CheckMinecraftCatalogue;
use Wyvern\Console\Commands\InstallModpack;
use Wyvern\Filament\Widgets\ShortcutsWidget;
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
    public function boot(): void
    {
        // Filament serves its own panel stylesheet, and Pelican injects app.css through
        // STYLES_BEFORE. Ours has to land after both to reshape their surfaces, so it
        // goes on the other hook rather than relying on Vite's emission order.
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn () => Blade::render("@vite(['resources/css/wyvern-theme.css'])"),
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckContentLibrary::class,
                CheckMinecraftCatalogue::class,
                InstallModpack::class,
            ]);
        }
    }
}
