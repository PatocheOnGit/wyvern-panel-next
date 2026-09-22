<?php

namespace Wyvern\Minecraft\Players;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Wyvern\Minecraft\Files\MinecraftFiles;

/**
 * The whitelist, operators and bans.
 *
 * A running server owns these files and overwrites them, so changes go through its
 * console. A stopped one is edited on disk, which means resolving UUIDs ourselves.
 */
class PlayerLists
{
    public const FILES = [
        'whitelist' => '/whitelist.json',
        'ops' => '/ops.json',
        'bans' => '/banned-players.json',
        'ip_bans' => '/banned-ips.json',
    ];

    public const USERCACHE = '/usercache.json';

    private const NAME = '/^\.?[A-Za-z0-9_]{1,16}$/';

    public function __construct(private readonly MinecraftFiles $files) {}

    /** @return list<array<string, mixed>> */
    public function entries(Server $server, string $list): array
    {
        return $this->files->readJsonList($server, self::FILES[$list]);
    }

    /** @return list<array<string, mixed>> players the server has seen, newest first */
    public function recent(Server $server): array
    {
        return collect($this->files->readJsonList($server, self::USERCACHE))
            ->filter(fn (array $p) => isset($p['name'], $p['uuid']))
            ->sortByDesc('expiresOn')
            ->values()
            ->all();
    }

    /** @throws RuntimeException with a message fit for the user */
    public function add(Server $server, string $list, string $value, ?string $reason = null): void
    {
        $value = trim($value);
        $reason = $this->reason($reason);
        $this->validate($list, $value);

        if ($this->isRunning($server)) {
            $this->command($server, match ($list) {
                'whitelist' => "whitelist add $value",
                'ops' => "op $value",
                'bans' => trim("ban $value $reason"),
                default => trim("ban-ip $value $reason"),
            });

            return;
        }

        $entries = $this->entries($server, $list);
        $key = $list === 'ip_bans' ? 'ip' : 'name';

        if (collect($entries)->contains(fn (array $e) => strcasecmp((string) ($e[$key] ?? ''), $value) === 0)) {
            return;
        }

        $entry = $list === 'ip_bans' ? ['ip' => $value] : $this->identity($server, $value);

        $entries[] = match ($list) {
            'whitelist' => $entry,
            'ops' => $entry + ['level' => $this->opLevel($server), 'bypassesPlayerLimit' => false],
            default => $entry + [
                'created' => now()->format('Y-m-d H:i:s O'),
                'source' => 'Server',
                'expires' => 'forever',
                'reason' => $reason !== '' ? $reason : 'Banned by an operator.',
            ],
        };

        $this->files->writeJsonList($server, self::FILES[$list], $entries);
    }

    public function remove(Server $server, string $list, string $value): void
    {
        $this->validate($list, $value);

        if ($this->isRunning($server)) {
            $this->command($server, match ($list) {
                'whitelist' => "whitelist remove $value",
                'ops' => "deop $value",
                'bans' => "pardon $value",
                default => "pardon-ip $value",
            });

            return;
        }

        $key = $list === 'ip_bans' ? 'ip' : 'name';
        $entries = array_filter(
            $this->entries($server, $list),
            fn (array $e) => strcasecmp((string) ($e[$key] ?? ''), $value) !== 0,
        );

        $this->files->writeJsonList($server, self::FILES[$list], array_values($entries));
    }

    public function kick(Server $server, string $name, ?string $reason = null): void
    {
        $this->validate('whitelist', $name);
        $this->command($server, trim("kick $name " . $this->reason($reason)));
    }

    public function setWhitelist(Server $server, bool $enabled): void
    {
        if ($this->isRunning($server)) {
            $this->command($server, 'whitelist ' . ($enabled ? 'on' : 'off'));

            return;
        }

        $properties = $this->files->properties($server);

        if ($properties !== null) {
            $properties->set('white-list', $enabled ? 'true' : 'false');
            $this->files->saveProperties($server, $properties);
        }
    }

    public function isRunning(Server $server): bool
    {
        return $server->retrieveStatus()->isStartingOrRunning();
    }

    private function command(Server $server, string $command): void
    {
        $server->send($command);
        // The server rewrites the file within a tick or two; read after it has.
        usleep(800_000);
    }

    /** @throws RuntimeException */
    private function validate(string $list, string $value): void
    {
        if (!isset(self::FILES[$list])) {
            throw new RuntimeException('Unknown list.');
        }

        $valid = $list === 'ip_bans'
            ? filter_var($value, FILTER_VALIDATE_IP) !== false
            : preg_match(self::NAME, $value) === 1;

        if (!$valid) {
            throw new RuntimeException(trans($list === 'ip_bans' ? 'wyvern.players.errors.ip' : 'wyvern.players.errors.name'));
        }
    }

    /** One line, no control characters: it ends up in a console command. */
    private function reason(?string $reason): string
    {
        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $reason)), 0, 200);
    }

    /**
     * @return array{uuid: string, name: string}
     *
     * @throws RuntimeException
     */
    private function identity(Server $server, string $name): array
    {
        foreach ($this->recent($server) as $player) {
            if (strcasecmp($player['name'], $name) === 0) {
                return ['uuid' => $player['uuid'], 'name' => $player['name']];
            }
        }

        $onlineMode = ($this->files->properties($server)?->get('online-mode', 'true') ?? 'true') === 'true';

        if (!$onlineMode) {
            return ['uuid' => self::offlineUuid($name), 'name' => $name];
        }

        $response = Http::timeout(5)->get('https://api.mojang.com/users/profiles/minecraft/' . rawurlencode($name));

        if (!$response->successful() || !is_string($response->json('id'))) {
            throw new RuntimeException(trans('wyvern.players.errors.unknown_account', ['name' => $name]));
        }

        return ['uuid' => self::dashed($response->json('id')), 'name' => $response->json('name') ?? $name];
    }

    private function opLevel(Server $server): int
    {
        return (int) ($this->files->properties($server)?->get('op-permission-level', '4') ?? 4);
    }

    /** The UUID an offline-mode server derives from a name, as Java's nameUUIDFromBytes does. */
    public static function offlineUuid(string $name): string
    {
        $bytes = md5('OfflinePlayer:' . $name, true);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x30);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return self::dashed(bin2hex($bytes));
    }

    private static function dashed(string $hex): string
    {
        return preg_replace('/^(.{8})(.{4})(.{4})(.{4})(.{12})$/', '$1-$2-$3-$4-$5', strtolower($hex)) ?? $hex;
    }
}
