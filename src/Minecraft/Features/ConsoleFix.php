<?php

namespace Wyvern\Minecraft\Features;

use App\Enums\SubuserPermission;
use App\Extensions\Features\FeatureSchemaInterface;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Wyvern\Minecraft\Files\MinecraftFiles;

/**
 * A console line the panel recognises, and what to do about it.
 *
 * Wings matches the line but does not pass it to the action, so fixes that need
 * details read the end of logs/latest.log instead.
 */
abstract class ConsoleFix implements FeatureSchemaInterface
{
    public function __construct(private readonly MinecraftFiles $files) {}

    public function authorize(User $user, Server $server): bool
    {
        return $user->can(SubuserPermission::ControlConsole, $server);
    }

    protected function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    protected function logTail(int $bytes = 65536): string
    {
        try {
            return substr($this->files->read($this->server(), '/logs/latest.log') ?? '', -$bytes);
        } catch (\Throwable) {
            return '';
        }
    }
}
