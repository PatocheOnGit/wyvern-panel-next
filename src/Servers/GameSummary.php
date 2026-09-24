<?php

namespace Wyvern\Servers;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Wyvern\Content\ServerProfile;
use Wyvern\FiveM\FiveMServer;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\InstallRecords;
use Wyvern\Minecraft\Players\StatusPing;

/** What a server runs and who is on it, for cards and the console. Cached briefly. */
class GameSummary
{
    public function __construct(
        private readonly InstallRecords $records,
        private readonly StatusPing $ping,
        private readonly MinecraftFiles $files,
    ) {}

    /**
     * The game's mark for a card: the loader's logo, served locally, or an icon.
     *
     * @return array{logo?: string, icon?: string}|null
     */
    public function emblem(Server $server): ?array
    {
        $loader = ServerProfile::of($server)->loader;

        if ($loader !== null) {
            return ['logo' => $loader->logo()];
        }

        $fivem = FiveMServer::of($server);

        if ($fivem !== null) {
            return ['icon' => $fivem->game() === 'redm' ? 'tabler-horse' : 'tabler-car'];
        }

        return null;
    }

    /** "Paper 26.2", "FiveM · build 35245", or null for anything else. */
    public function software(Server $server): ?string
    {
        return Cache::remember("wyvern.summary.software.{$server->uuid}", now()->addMinutes(5), function () use ($server) {
            $profile = ServerProfile::of($server);

            if ($profile->loader !== null) {
                return trim($profile->loader->label() . ' ' . ($profile->gameVersion($this->records) ?? ''));
            }

            $fivem = FiveMServer::of($server);

            if ($fivem === null) {
                return null;
            }

            try {
                $build = json_decode($this->files->read($server, '/.wyvern/install.json') ?? '', true)['build'] ?? null;
            } catch (\Throwable) {
                $build = null;
            }

            $game = $fivem->label();

            return $build ? $game . ' · build ' . strtok((string) $build, '-') : $game;
        }) ?: null;
    }

    /** @return array{online: int, max: int}|null null when the server is off or does not answer */
    public function players(Server $server): ?array
    {
        if (!$server->retrieveStatus()->isStartingOrRunning()) {
            return null;
        }

        $cached = Cache::remember("wyvern.summary.players.{$server->uuid}", now()->addSeconds(20), fn () => $this->fetchPlayers($server) ?? []);

        return $cached === [] ? null : $cached;
    }

    /** @return array{online: int, max: int}|null */
    private function fetchPlayers(Server $server): ?array
    {
        if (ServerProfile::of($server)->loader !== null) {
            $status = $this->ping->ping($server, 1.0);

            return $status === null ? null : ['online' => $status['online'], 'max' => $status['max']];
        }

        $fivem = FiveMServer::of($server);

        if ($fivem === null) {
            return null;
        }

        try {
            $dynamic = Http::timeout(1)->get('http://' . $fivem->host() . ':' . $fivem->port() . '/dynamic.json')->json();
        } catch (\Throwable) {
            return null;
        }

        return is_array($dynamic) && isset($dynamic['clients'])
            ? ['online' => (int) $dynamic['clients'], 'max' => (int) ($dynamic['sv_maxclients'] ?? 0)]
            : null;
    }
}
