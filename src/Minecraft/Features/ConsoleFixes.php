<?php

namespace Wyvern\Minecraft\Features;

use Wyvern\FiveM\Features\GameBuild;
use Wyvern\FiveM\Features\LicenseKey;
use Wyvern\FiveM\Features\PortInUse as FiveMPortInUse;
use Wyvern\Minecraft\Files\MinecraftFiles;

final class ConsoleFixes
{
    /** @return ConsoleFix[] */
    public static function all(MinecraftFiles $files): array
    {
        return [
            new PortInUse($files),
            new OutOfMemory($files),
            new MissingDependency($files),
            new ClientOnlyMod($files),
            new NewerWorld($files),
            new LicenseKey($files),
            new FiveMPortInUse($files),
            new GameBuild($files),
        ];
    }
}
