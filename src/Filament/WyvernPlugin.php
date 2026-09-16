<?php

namespace Wyvern\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Wyvern\Filament\Server\Pages\Content;
use Wyvern\Filament\Server\Pages\Version;

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
