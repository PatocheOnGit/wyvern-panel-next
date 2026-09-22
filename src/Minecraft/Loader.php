<?php

namespace Wyvern\Minecraft;

/**
 * The server flavours Wyvern can install.
 *
 * Each one is served by a different upstream API, so the enum carries only identity;
 * the catalogue behind it knows how to talk to its own source.
 */
enum Loader: string
{
    case Vanilla = 'vanilla';
    case Paper = 'paper';
    case Purpur = 'purpur';
    case Folia = 'folia';
    case Fabric = 'fabric';
    case Quilt = 'quilt';
    case Forge = 'forge';
    case NeoForge = 'neoforge';

    public function label(): string
    {
        return match ($this) {
            self::Vanilla => 'Vanilla',
            self::Paper => 'Paper',
            self::Purpur => 'Purpur',
            self::Folia => 'Folia',
            self::Fabric => 'Fabric',
            self::Quilt => 'Quilt',
            self::Forge => 'Forge',
            self::NeoForge => 'NeoForge',
        };
    }

    /** The flavour's own mark, served from the panel rather than a CDN. */
    public function logo(): string
    {
        return match ($this) {
            self::Vanilla => '/wyvern/loaders/vanilla.svg',
            self::Paper => '/wyvern/loaders/paper.webp',
            self::Purpur => '/wyvern/loaders/purpur.svg',
            self::Folia => '/wyvern/loaders/folia.svg',
            self::Fabric => '/wyvern/loaders/fabric.png',
            self::Quilt => '/wyvern/loaders/quilt.svg',
            self::Forge => '/wyvern/loaders/forge.png',
            self::NeoForge => '/wyvern/loaders/neoforge.png',
        };
    }

    /** What the flavour is for, in one line, for the picker. */
    public function summary(): string
    {
        return match ($this) {
            self::Vanilla => 'Mojang\'s own server. No plugins, no mods.',
            self::Paper => 'High-performance Bukkit fork. Plugins.',
            self::Purpur => 'Paper fork with extra gameplay configuration. Plugins.',
            self::Folia => 'Paper fork that runs regions on separate threads. Folia plugins only.',
            self::Fabric => 'Lightweight mod loader. Mods.',
            self::Quilt => 'Fabric fork. Quilt and most Fabric mods.',
            self::Forge => 'The long-standing mod loader. Mods.',
            self::NeoForge => 'Forge successor, maintained fork. Mods.',
        };
    }

    /**
     * Which Modrinth loader facets match content that runs on this flavour.
     *
     * @return string[]
     */
    public function contentLoaders(): array
    {
        return match ($this) {
            self::Vanilla => [],
            self::Paper => ['paper', 'bukkit', 'spigot', 'folia'],
            self::Purpur => ['purpur', 'paper', 'bukkit', 'spigot'],
            // Folia breaks plugins that do not declare support for it.
            self::Folia => ['folia'],
            self::Fabric => ['fabric'],
            self::Quilt => ['quilt', 'fabric'],
            self::Forge => ['forge'],
            self::NeoForge => ['neoforge'],
        };
    }

    /** Where installed content belongs on disk. */
    public function contentDirectory(): string
    {
        return match ($this) {
            self::Paper, self::Purpur, self::Folia => 'plugins',
            self::Fabric, self::Quilt, self::Forge, self::NeoForge => 'mods',
            self::Vanilla => '',
        };
    }

    public function acceptsContent(): bool
    {
        return $this !== self::Vanilla;
    }

    /** The loader key a Modrinth modpack index uses in its dependencies. */
    public static function fromModpackKey(string $key): ?self
    {
        return self::tryFrom(str_replace('-loader', '', $key));
    }
}
