<?php

namespace Wyvern\Filament\Server\Pages\FiveM\Concerns;

use App\Models\Server;
use Filament\Facades\Filament;
use Wyvern\FiveM\FiveMServer;

/** Shared by the FiveM pages: the tenant, and access only for FiveM and RedM servers. */
trait FiveMPage
{
    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public function fivem(): FiveMServer
    {
        /** @var FiveMServer */
        return FiveMServer::of($this->server());
    }

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server
            && !$server->isInConflictState()
            && FiveMServer::is($server)
            && (static::PERMISSION === null || (user()?->can(static::PERMISSION, $server) ?? false));
    }

    public static function getNavigationLabel(): string
    {
        return trans(static::LABEL);
    }

    public function getTitle(): string
    {
        return trans(static::LABEL);
    }
}
