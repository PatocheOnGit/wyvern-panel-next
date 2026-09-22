<?php

namespace Wyvern\Filament\Forms;

use App\Enums\SubuserPermission;
use App\Models\Server;
use Filament\Forms\Components\Toggle;

/** "Back up first", offered before anything that rewrites the server's files. Off by default. */
final class BackupToggle
{
    public static function make(Server $server): Toggle
    {
        return Toggle::make('backup')
            ->label(trans('wyvern.backup.label'))
            ->default(false)
            ->helperText(fn () => match (true) {
                $server->backup_limit <= 0 => trans('wyvern.backup.disabled'),
                !self::available($server) => trans('wyvern.backup.full', ['limit' => $server->backup_limit]),
                default => trans('wyvern.backup.help'),
            })
            ->disabled(fn () => !self::available($server))
            ->visible(fn () => user()?->can(SubuserPermission::BackupCreate, $server) ?? false)
            ->columnSpanFull();
    }

    public static function available(Server $server): bool
    {
        return $server->backup_limit > 0
            && $server->backups()->nonFailed()->count() < $server->backup_limit;
    }
}
