<?php

namespace Wyvern\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Wyvern\Filament\Server\Pages\Content;
use Wyvern\Filament\Server\Pages\Version;
use Wyvern\Filament\Widgets\FleetOverview;
use Wyvern\Filament\Widgets\NodeHealth;
use Wyvern\Filament\Widgets\RecentActivity;

/**
 * Wyvern's own pages, registered the way Filament expects.
 *
 * Going through a plugin rather than adding to the panel provider's discoverPages()
 * means our code stays in src/ and the diff against upstream is one line.
 */
class WyvernPlugin implements Plugin
{
    public function getId(): string
    {
        return 'wyvern';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            // discoverWidgets() only scans app/Filament/Admin/Widgets, and ours lives in
            // src/ — registering through the plugin keeps it there and leaves the panel
            // provider untouched.
            $panel->widgets([
                FleetOverview::class,
                NodeHealth::class,
                RecentActivity::class,
            ]);

            return;
        }

        if ($panel->getId() !== 'server') {
            return;
        }

        $panel->pages([
            Version::class,
            Content::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
