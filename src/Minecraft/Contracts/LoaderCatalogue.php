<?php

namespace Wyvern\Minecraft\Contracts;

use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

interface LoaderCatalogue
{
    public function loader(): Loader;

    /**
     * Minecraft versions this flavour publishes, newest first.
     *
     * @return string[]
     */
    public function gameVersions(): array;

    /**
     * Builds for one game version, newest first.
     *
     * Flavours that publish a single artefact per game version return an empty array;
     * the caller then passes null as the build.
     *
     * @return string[]
     */
    public function builds(string $gameVersion): array;

    /** Null when the combination does not exist upstream. */
    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary;
}
