<?php

namespace Wyvern;

use App\Enums\HeaderWidgetPosition;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Wyvern\Console\Commands\CheckContentLibrary;
use Wyvern\Console\Commands\CheckMinecraftCatalogue;
use Wyvern\Console\Commands\InstallModpack;
use Wyvern\Filament\Widgets\ShortcutsWidget;

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
