<?php

namespace Wyvern\Content\Modpack;

final readonly class ModpackFile
{
    public function __construct(
        public string $path,
        public string $url,
        public ?int $size,
        public string $serverEnv,
    ) {}

    /** @param array<string, mixed> $entry */
    public static function fromIndex(array $entry): ?self
    {
        $path = $entry['path'] ?? null;
        $url = $entry['downloads'][0] ?? null;

        // An entry without somewhere to go, or anywhere to come from, is not actionable.
        if (!is_string($path) || !is_string($url)) {
            return null;
        }

        // Refuse to write outside the server. Nothing in a published pack does this,
        // which is exactly why it would go unnoticed if one did.
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        return new self(
            path: $path,
            url: $url,
            size: $entry['fileSize'] ?? null,
            serverEnv: $entry['env']['server'] ?? 'required',
        );
    }

    /**
     * The Modrinth project this came from, read out of the CDN url.
     *
     * The index does not carry it, but every download points at
     * cdn.modrinth.com/data/<project>/versions/... so it can be recovered — which is
     * what makes it possible to ask Modrinth whether the mod runs on a server at all.
     */
    public function projectId(): ?string
    {
        return preg_match('#//cdn\.modrinth\.com/data/([A-Za-z0-9]+)/#', $this->url, $m) === 1
            ? $m[1]
            : null;
    }

    public function runsOnServer(): bool
    {
        return $this->serverEnv !== 'unsupported';
    }

    public function directory(): string
    {
        $dir = dirname($this->path);

        return $dir === '.' ? '/' : '/' . $dir;
    }

    public function filename(): string
    {
        return basename($this->path);
    }
}
