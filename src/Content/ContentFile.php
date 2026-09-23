<?php

namespace Wyvern\Content;

/**
 * A downloadable release of a project.
 *
 * `url` can be null: CurseForge lets an author forbid third-party distribution, and
 * then it returns the file's metadata with no download link at all. Anything acting on
 * a file has to handle that rather than assume a url is always there.
 */
final readonly class ContentFile
{
    public function __construct(
        public string $source,
        public string $id,
        public string $filename,
        public ?string $url,
        public string $versionName,
        /** @var string[] */
        public array $gameVersions = [],
        /** @var string[] */
        public array $loaders = [],
        public ?int $size = null,
        public ?string $releaseType = null,
        public ?string $projectId = null,
        public ?string $sha1 = null,
        /** @var list<array{project: ?string, version: ?string}> required dependencies */
        public array $dependencies = [],
    ) {}

    public function isDownloadable(): bool
    {
        return filled($this->url);
    }

    public function sizeForHumans(): ?string
    {
        if ($this->size === null) {
            return null;
        }

        return $this->size >= 1048576
            ? round($this->size / 1048576, 1) . ' MiB'
            : round($this->size / 1024) . ' KiB';
    }
}
