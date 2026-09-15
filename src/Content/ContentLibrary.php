<?php

namespace Wyvern\Content;

use Wyvern\Content\Contracts\ContentSource;
use Wyvern\Content\Sources\CurseForgeSource;
use Wyvern\Content\Sources\ModrinthSource;
use Wyvern\Minecraft\Loader;

/**
 * The libraries Wyvern can install from.
 *
 * Only sources that can actually answer are offered: CurseForge needs an approved key
 * and hides itself without one, so the picker never shows a tab that returns nothing.
 */
class ContentLibrary
{
    /** @var ContentSource[] */
    private array $sources;

    public function __construct()
    {
        $this->sources = [
            new ModrinthSource(),
            new CurseForgeSource(),
        ];
    }

    /** @return ContentSource[] */
    public function available(): array
    {
        return array_values(array_filter(
            $this->sources,
            fn (ContentSource $source): bool => $source->isAvailable(),
        ));
    }

    public function source(string $key): ?ContentSource
    {
        foreach ($this->sources as $source) {
            if ($source->key() === $key) {
                return $source;
            }
        }

        return null;
    }

    /** @return ContentProject[] */
    public function search(
        string $sourceKey,
        string $query,
        ContentType $type,
        ?Loader $loader = null,
        ?string $gameVersion = null,
        int $limit = 24,
    ): array {
        return $this->source($sourceKey)?->search($query, $type, $loader, $gameVersion, $limit) ?? [];
    }

    /** @return ContentFile[] */
    public function files(
        string $sourceKey,
        string $projectId,
        ?Loader $loader = null,
        ?string $gameVersion = null,
    ): array {
        return $this->source($sourceKey)?->files($projectId, $loader, $gameVersion) ?? [];
    }
}
