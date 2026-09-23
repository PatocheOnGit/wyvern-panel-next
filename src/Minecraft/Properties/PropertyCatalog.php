<?php

namespace Wyvern\Minecraft\Properties;

/**
 * What the panel knows about each server.properties key.
 *
 * Only keys present in the file are shown, so one list serves every Minecraft version:
 * keys a version dropped simply never appear, and keys the panel does not know land in
 * the Other group as plain text.
 */
final class PropertyCatalog
{
    /** Keys Wings rewrites on every boot from the allocation. */
    public const MANAGED = ['server-ip', 'server-port', 'query.port'];

    public const GROUPS = ['general', 'world', 'players', 'resource_pack', 'performance', 'remote', 'other'];

    /**
     * @var array<string, array{group: string, type: string, options?: list<string>, min?: int, max?: int}>
     */
    private const DEFINITIONS = [
        'motd' => ['group' => 'general', 'type' => 'motd'],
        'max-players' => ['group' => 'general', 'type' => 'int', 'min' => 0],
        'gamemode' => ['group' => 'general', 'type' => 'enum', 'options' => ['survival', 'creative', 'adventure', 'spectator']],
        'force-gamemode' => ['group' => 'general', 'type' => 'bool'],
        'difficulty' => ['group' => 'general', 'type' => 'enum', 'options' => ['peaceful', 'easy', 'normal', 'hard']],
        'hardcore' => ['group' => 'general', 'type' => 'bool'],
        'pvp' => ['group' => 'general', 'type' => 'bool'],
        'online-mode' => ['group' => 'general', 'type' => 'bool'],
        'allow-flight' => ['group' => 'general', 'type' => 'bool'],
        'white-list' => ['group' => 'general', 'type' => 'bool'],
        'enforce-whitelist' => ['group' => 'general', 'type' => 'bool'],

        'level-name' => ['group' => 'world', 'type' => 'string'],
        'level-seed' => ['group' => 'world', 'type' => 'string'],
        'level-type' => ['group' => 'world', 'type' => 'enum', 'options' => ['minecraft:normal', 'minecraft:flat', 'minecraft:large_biomes', 'minecraft:amplified', 'minecraft:single_biome_surface']],
        'generator-settings' => ['group' => 'world', 'type' => 'string'],
        'generate-structures' => ['group' => 'world', 'type' => 'bool'],
        'allow-nether' => ['group' => 'world', 'type' => 'bool'],
        'spawn-monsters' => ['group' => 'world', 'type' => 'bool'],
        'spawn-animals' => ['group' => 'world', 'type' => 'bool'],
        'spawn-npcs' => ['group' => 'world', 'type' => 'bool'],
        'spawn-protection' => ['group' => 'world', 'type' => 'int', 'min' => 0],
        'max-world-size' => ['group' => 'world', 'type' => 'int', 'min' => 1, 'max' => 29999984],
        'view-distance' => ['group' => 'world', 'type' => 'int', 'min' => 2, 'max' => 32],
        'simulation-distance' => ['group' => 'world', 'type' => 'int', 'min' => 2, 'max' => 32],
        'initial-enabled-packs' => ['group' => 'world', 'type' => 'string'],
        'initial-disabled-packs' => ['group' => 'world', 'type' => 'string'],

        'player-idle-timeout' => ['group' => 'players', 'type' => 'int', 'min' => 0],
        'op-permission-level' => ['group' => 'players', 'type' => 'enum', 'options' => ['1', '2', '3', '4']],
        'function-permission-level' => ['group' => 'players', 'type' => 'enum', 'options' => ['1', '2', '3', '4']],
        'enable-command-block' => ['group' => 'players', 'type' => 'bool'],
        'hide-online-players' => ['group' => 'players', 'type' => 'bool'],
        'enforce-secure-profile' => ['group' => 'players', 'type' => 'bool'],
        'prevent-proxy-connections' => ['group' => 'players', 'type' => 'bool'],
        'accepts-transfers' => ['group' => 'players', 'type' => 'bool'],
        'log-ips' => ['group' => 'players', 'type' => 'bool'],
        'broadcast-console-to-ops' => ['group' => 'players', 'type' => 'bool'],
        'bug-report-link' => ['group' => 'players', 'type' => 'string'],

        'resource-pack' => ['group' => 'resource_pack', 'type' => 'string'],
        'resource-pack-sha1' => ['group' => 'resource_pack', 'type' => 'string'],
        'resource-pack-id' => ['group' => 'resource_pack', 'type' => 'string'],
        'require-resource-pack' => ['group' => 'resource_pack', 'type' => 'bool'],
        'resource-pack-prompt' => ['group' => 'resource_pack', 'type' => 'string'],

        'pause-when-empty-seconds' => ['group' => 'performance', 'type' => 'int', 'min' => -1],
        'network-compression-threshold' => ['group' => 'performance', 'type' => 'int', 'min' => -1],
        'rate-limit' => ['group' => 'performance', 'type' => 'int', 'min' => 0],
        'max-tick-time' => ['group' => 'performance', 'type' => 'int', 'min' => -1],
        'entity-broadcast-range-percentage' => ['group' => 'performance', 'type' => 'int', 'min' => 10, 'max' => 1000],
        'max-chained-neighbor-updates' => ['group' => 'performance', 'type' => 'int'],
        'sync-chunk-writes' => ['group' => 'performance', 'type' => 'bool'],
        'use-native-transport' => ['group' => 'performance', 'type' => 'bool'],
        'region-file-compression' => ['group' => 'performance', 'type' => 'enum', 'options' => ['deflate', 'lz4', 'none']],

        'enable-status' => ['group' => 'remote', 'type' => 'bool'],
        'enable-query' => ['group' => 'remote', 'type' => 'bool'],
        'enable-rcon' => ['group' => 'remote', 'type' => 'bool'],
        'rcon.port' => ['group' => 'remote', 'type' => 'int', 'min' => 1, 'max' => 65535],
        'rcon.password' => ['group' => 'remote', 'type' => 'password'],
        'broadcast-rcon-to-ops' => ['group' => 'remote', 'type' => 'bool'],
        'enable-jmx-monitoring' => ['group' => 'remote', 'type' => 'bool'],
    ];

    /** What a fresh vanilla server writes, for the "changed" mark and the reset. */
    private const DEFAULTS = [
        'motd' => 'A Minecraft Server', 'max-players' => '20', 'gamemode' => 'survival', 'force-gamemode' => 'false',
        'difficulty' => 'easy', 'hardcore' => 'false', 'pvp' => 'true', 'online-mode' => 'true', 'allow-flight' => 'false',
        'white-list' => 'false', 'enforce-whitelist' => 'false', 'level-name' => 'world', 'level-seed' => '',
        'level-type' => 'minecraft:normal', 'generator-settings' => '{}', 'generate-structures' => 'true',
        'allow-nether' => 'true', 'spawn-monsters' => 'true', 'spawn-animals' => 'true', 'spawn-npcs' => 'true',
        'spawn-protection' => '16', 'max-world-size' => '29999984', 'view-distance' => '10', 'simulation-distance' => '10',
        'initial-enabled-packs' => 'vanilla', 'initial-disabled-packs' => '', 'player-idle-timeout' => '0',
        'op-permission-level' => '4', 'function-permission-level' => '2', 'enable-command-block' => 'false',
        'hide-online-players' => 'false', 'enforce-secure-profile' => 'true', 'prevent-proxy-connections' => 'false',
        'accepts-transfers' => 'false', 'log-ips' => 'true', 'broadcast-console-to-ops' => 'true', 'bug-report-link' => '',
        'resource-pack' => '', 'resource-pack-sha1' => '', 'resource-pack-id' => '', 'require-resource-pack' => 'false',
        'resource-pack-prompt' => '', 'pause-when-empty-seconds' => '60', 'network-compression-threshold' => '256',
        'rate-limit' => '0', 'max-tick-time' => '60000', 'entity-broadcast-range-percentage' => '100',
        'max-chained-neighbor-updates' => '1000000', 'sync-chunk-writes' => 'true', 'use-native-transport' => 'true',
        'region-file-compression' => 'deflate', 'enable-status' => 'true', 'enable-query' => 'false',
        'enable-rcon' => 'false', 'rcon.port' => '25575', 'rcon.password' => '', 'broadcast-rcon-to-ops' => 'true',
        'enable-jmx-monitoring' => 'false',
    ];

    public static function default(string $key): ?string
    {
        return self::DEFAULTS[$key] ?? null;
    }

    /** @return array{group: string, type: string, options?: list<string>, min?: int, max?: int} */
    public static function definition(string $key): array
    {
        return self::DEFINITIONS[$key] ?? ['group' => 'other', 'type' => 'string'];
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::DEFINITIONS[$key]);
    }

    public static function isManaged(string $key): bool
    {
        return in_array($key, self::MANAGED, true);
    }

    /**
     * Catalog order first, then unknown keys alphabetically.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function sort(array $keys): array
    {
        $order = array_flip(array_keys(self::DEFINITIONS));

        usort($keys, fn (string $a, string $b) => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);

        return $keys;
    }

    /** A key as a translation and form field name: dots nest in both. */
    public static function slug(string $key): string
    {
        return str_replace(['.', '-'], '_', $key);
    }
}
