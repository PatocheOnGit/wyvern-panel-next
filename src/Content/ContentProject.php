<?php

namespace Wyvern\Content;

/**
 * One searchable thing, whichever library it came from.
 *
 * `id` is whatever that source needs to look the project up again — a slug on
 * Modrinth, a numeric id on CurseForge — so it is opaque and always travels with
 * `source`.
 */
final readonly class ContentProject
{
    public function __construct(
        public string $source,
        public string $id,
        public string $title,
        public string $summary,
        public ContentType $type,
        public int $downloads,
        public ?string $iconUrl = null,
        public ?string $author = null,
        public ?string $pageUrl = null,
        /** @var string[] */
        public array $categories = [],
    ) {}

    /** 71 586 100 reads as noise in a card; 71.6M does not. */
    public function downloadsForHumans(): string
    {
        return match (true) {
            $this->downloads >= 1_000_000 => round($this->downloads / 1_000_000, 1) . 'M',
            $this->downloads >= 1_000 => round($this->downloads / 1_000) . 'K',
            default => (string) $this->downloads,
        };
    }
}
