<?php

namespace Wyvern\Navigation;

use App\Enums\TablerIcon;
use App\Filament\Server\Pages\Console;
use App\Models\Server;
use Filament\Facades\Filament;
use Throwable;

/**
 * Everything the command palette can jump to, built once per page load.
 *
 * Built here rather than searched on keystroke, and filtered in the browser, for one
 * structural reason: a Livewire update posts to /livewire/update, which is not a panel
 * route, so Filament::getCurrentPanel() and the current tenant are not resolved there.
 * A palette that asked the server on every keypress would work on first render and
 * return nothing afterwards. Assembling the list while the panel context exists avoids
 * that entirely, and filtering locally is instant.
 *
 * The cost is a cap: only the first fifty accessible servers are offered. This is a fast
 * switcher for the servers someone actually works on, not an exhaustive search — the
 * admin panel keeps its global search modal for that.
 */
final class CommandItems
{
    private const SERVER_LIMIT = 50;

    /** @return list<array{group: string, label: string, sublabel: string|null, icon: string, url: string}> */
    public static function all(): array
    {
        return [...self::pages(), ...self::servers()];
    }

    /**
     * The current panel's own navigation, so the palette can never offer a destination
     * the sidebar does not, or miss one it gained.
     *
     * @return list<array{group: string, label: string, sublabel: string|null, icon: string, url: string}>
     */
    private static function pages(): array
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null) {
            return [];
        }

        $items = [];

        foreach ($panel->getNavigation() as $group) {
            $groupLabel = $group->getLabel();

            foreach ($group->getItems() as $item) {
                $url = $item->getUrl();

                if (blank($url)) {
                    continue;
                }

                $icon = $item->getIcon();

                $items[] = [
                    'group' => trans('wyvern.palette.pages'),
                    'label' => (string) $item->getLabel(),
                    'sublabel' => filled($groupLabel) ? (string) $groupLabel : null,
                    'icon' => self::iconValue($icon) ?? TablerIcon::ArrowRight->value,
                    'url' => $url,
                ];
            }
        }

        return $items;
    }

    /** @return list<array{group: string, label: string, sublabel: string|null, icon: string, url: string}> */
    private static function servers(): array
    {
        $user = user();

        if ($user === null) {
            return [];
        }

        $servers = $user->accessibleServers()
            ->with(['allocation', 'egg'])
            ->orderBy('name')
            ->limit(self::SERVER_LIMIT)
            ->get();

        $items = [];

        foreach ($servers as $server) {
            /** @var Server $server */
            try {
                $url = Console::getUrl(panel: 'server', tenant: $server);
            } catch (Throwable) {
                // A server whose console URL cannot be built — mid-transfer, or a panel
                // the user cannot reach — is skipped rather than offered as a dead row.
                continue;
            }

            $items[] = [
                'group' => trans('wyvern.palette.servers'),
                'label' => $server->name,
                'sublabel' => $server->allocation?->address ?? $server->egg?->name,
                'icon' => TablerIcon::BrandDocker->value,
                'url' => $url,
            ];
        }

        return $items;
    }

    private static function iconValue(mixed $icon): ?string
    {
        if ($icon instanceof \BackedEnum) {
            return (string) $icon->value;
        }

        return is_string($icon) ? $icon : null;
    }
}
