<?php

namespace Wyvern\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * The row of host links above the server cards.
 *
 * It reads config('wyvern.shortcuts') and drops anything without a url, so a fresh
 * install shows only what has actually been configured instead of a row of dead
 * buttons. It registers itself through ListServers::registerCustomHeaderWidgets(),
 * which is the extension point Pelican already provides — no upstream file is touched.
 */
class ShortcutsWidget extends Widget
{
    protected string $view = 'wyvern.widgets.shortcuts';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::shortcuts() !== [];
    }

    /** @return array<int, array<string, string>> */
    public function getShortcuts(): array
    {
        return self::shortcuts();
    }

    /** @return array<int, array<string, string>> */
    private static function shortcuts(): array
    {
        $configured = config('wyvern.shortcuts', []);

        if (!is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            $configured,
            fn (mixed $shortcut): bool => is_array($shortcut) && filled($shortcut['url'] ?? null),
        ));
    }
}
