<?php

namespace Wyvern\Navigation;

use App\Filament\Admin\Resources\Nodes\NodeResource;
use App\Filament\Admin\Resources\Servers\ServerResource as AdminServerResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\App\Resources\Servers\ServerResource;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Server\Pages\Console;
use App\Filament\Server\Pages\Settings;
use App\Filament\Server\Resources\Activities\ActivityResource;
use App\Filament\Server\Resources\Backups\BackupResource;
use App\Filament\Server\Resources\Databases\DatabaseResource;
use App\Filament\Server\Resources\Files\FileResource;
use Filament\Facades\Filament;
use Throwable;

/**
 * The g-then-letter jumps, per panel.
 *
 * Letters are mnemonic against the English label rather than sequential, because the
 * point of a keyboard shortcut is not having to look: g-f is files whatever position
 * files happens to occupy in the sidebar this month.
 *
 * Built the same way as the command palette, and for the same reason — a panel and its
 * tenant resolve on a panel route and not on a Livewire update, so the list is assembled
 * while that context exists.
 */
final class Shortcuts
{
    /** @return array<string, array{label: string, url: string}> */
    public static function jumps(): array
    {
        /** @var array<string, callable(): ?array{label: string, url: string}> $candidates */
        $candidates = match (Filament::getCurrentPanel()?->getId()) {
            'server' => [
                'c' => fn () => self::page(Console::class),
                'f' => fn () => self::page(FileResource::class),
                'b' => fn () => self::page(BackupResource::class),
                'd' => fn () => self::page(DatabaseResource::class),
                'a' => fn () => self::page(ActivityResource::class),
                's' => fn () => self::page(Settings::class),
            ],
            'app' => [
                's' => fn () => self::page(ServerResource::class),
                // The profile is registered on the app panel and reached from all three, so
                // its URL has to name the panel rather than inherit the current one.
                'p' => fn () => [
                    'label' => trans('profile.title'),
                    'url' => EditProfile::getUrl(panel: 'app'),
                ],
            ],
            'admin' => [
                's' => fn () => self::page(AdminServerResource::class),
                'n' => fn () => self::page(NodeResource::class),
                'u' => fn () => self::page(UserResource::class),
            ],
            default => [],
        };

        $jumps = [];

        foreach ($candidates as $letter => $resolve) {
            // Same two failure modes as the palette: a destination the user cannot reach,
            // and a tenant not resolved yet. Either way the letter goes unbound rather than
            // bound to something that answers 403.
            try {
                if ($resolved = $resolve()) {
                    $jumps[$letter] = $resolved;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $jumps;
    }

    /**
     * @param  class-string  $class
     * @return array{label: string, url: string}|null
     */
    private static function page(string $class): ?array
    {
        if (!$class::canAccess()) {
            return null;
        }

        return [
            'label' => (string) $class::getNavigationLabel(),
            'url' => $class::getUrl(),
        ];
    }
}
