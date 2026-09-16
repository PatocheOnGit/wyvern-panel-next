<?php

namespace Wyvern\Navigation;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Nodes\NodeResource;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Server\Pages\Console;
use App\Filament\Server\Resources\Backups\BackupResource;
use App\Filament\Server\Resources\Files\FileResource;
use Filament\Facades\Filament;
use Throwable;

/**
 * The destinations a phone can reach without opening the drawer.
 *
 * A sidebar behind a hamburger costs two taps for every move, and the server panel keeps
 * thirteen destinations behind it. A bar is only worth its space where that is true, so
 * the client panel — which has three — does not get one and keeps the drawer.
 *
 * Each candidate is asked whether the current user may reach it, so a subuser without
 * the backup permission gets a shorter bar rather than a tab that answers 403.
 */
final class MobileTabs
{
    /** @return list<array{label: string, icon: string, url: string, active: bool}> */
    public static function for(string $panelId): array
    {
        /** @var list<array{0: class-string, 1: TablerIcon, 2: string}> $candidates */
        $candidates = match ($panelId) {
            'server' => [
                [Console::class, TablerIcon::Terminal2, 'filament.server.pages.console'],
                [FileResource::class, TablerIcon::Files, 'filament.server.resources.files.*'],
                [BackupResource::class, TablerIcon::FileZip, 'filament.server.resources.backups.*'],
            ],
            'admin' => [
                [ServerResource::class, TablerIcon::BrandDocker, 'filament.admin.resources.servers.*'],
                [NodeResource::class, TablerIcon::Server2, 'filament.admin.resources.nodes.*'],
                [UserResource::class, TablerIcon::Users, 'filament.admin.resources.users.*'],
            ],
            default => [],
        };

        $tabs = [];

        foreach ($candidates as [$class, $icon, $routePattern]) {
            // Two things throw rather than return here: a resource the user cannot reach,
            // and getUrl() on a tenant panel when the tenant is not resolved yet. Neither
            // is worth taking the page down for — the tab simply does not appear.
            try {
                if (!$class::canAccess()) {
                    continue;
                }

                $tabs[] = [
                    'label' => $class::getNavigationLabel(),
                    'icon' => $icon->value,
                    'url' => $class::getUrl(),
                    'active' => request()->routeIs($routePattern),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $tabs;
    }

    /** The bar earns its space only where the drawer hides a lot. */
    public static function shouldRender(): bool
    {
        if (!Filament::auth()->check()) {
            return false;
        }

        $panel = Filament::getCurrentPanel();

        return $panel !== null && in_array($panel->getId(), ['server', 'admin'], true);
    }
}
