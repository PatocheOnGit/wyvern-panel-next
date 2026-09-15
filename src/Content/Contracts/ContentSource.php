<?php

namespace Wyvern\Content\Contracts;

use Wyvern\Content\ContentFile;
use Wyvern\Content\ContentProject;
use Wyvern\Content\ContentType;
use Wyvern\Minecraft\Loader;

interface ContentSource
{
    /** Stable key, used in urls and stored against installed files. */
    public function key(): string;

    public function label(): string;

    /**
     * Whether this source can be used at all right now.
     *
     * CurseForge needs an approved API key, so it is off until one is configured.
     */
    public function isAvailable(): bool;

    /** @return ContentProject[] */
    public function search(
        string $query,
        ContentType $type,
        ?Loader $loader = null,
        ?string $gameVersion = null,
        int $limit = 24,
    ): array;

    /**
     * Releases of one project, newest first, already narrowed to what this server runs.
     *
     * @return ContentFile[]
     */
    public function files(
        string $projectId,
        ?Loader $loader = null,
        ?string $gameVersion = null,
        int $limit = 20,
    ): array;
}
