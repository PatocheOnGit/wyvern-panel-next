<?php

namespace Wyvern\Minecraft;

/**
 * What to fetch to stand a server up.
 *
 * Forge and NeoForge hand out an installer that has to be run once on the node; every
 * other flavour hands out a jar you can launch directly. The install script needs to
 * know which it is being given, so the distinction rides along with the url.
 */
final readonly class ServerBinary
{
    public function __construct(
        public Loader $loader,
        public string $gameVersion,
        public ?string $build,
        public string $url,
        public bool $isInstaller = false,
        public ?string $sha256 = null,
        public ?int $size = null,
    ) {}

    public function filename(): string
    {
        $name = basename(parse_url($this->url, PHP_URL_PATH) ?: '');

        return str_ends_with($name, '.jar') ? $name : 'server.jar';
    }
}
