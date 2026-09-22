<?php

namespace Wyvern\Minecraft\Features;

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
        ];
    }
}
