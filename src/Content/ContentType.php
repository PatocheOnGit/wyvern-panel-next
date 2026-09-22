<?php

namespace Wyvern\Content;

use Wyvern\Minecraft\Loader;

enum ContentType: string
{
    case Plugin = 'plugin';
    case Mod = 'mod';
    case Modpack = 'modpack';

    public function label(): string
    {
        return match ($this) {
            self::Plugin => 'Plugins',
            self::Mod => 'Mods',
            self::Modpack => 'Modpacks',
        };
    }

    /** Where a file of this kind belongs, relative to the server root. */
    public function directoryFor(Loader $loader): string
    {
        return match ($this) {
            self::Plugin => 'plugins',
            self::Mod => 'mods',
            // A modpack is an archive that rewrites the whole server, so it lands at
            // the root and is unpacked from there.
            self::Modpack => '',
        };
    }

    /** What a given flavour can actually accept. */
    public static function forLoader(Loader $loader): array
    {
        return match ($loader) {
            Loader::Vanilla => [],
            Loader::Paper, Loader::Purpur, Loader::Folia => [self::Plugin],
            Loader::Fabric, Loader::Quilt, Loader::Forge, Loader::NeoForge => [self::Mod, self::Modpack],
        };
    }
}
