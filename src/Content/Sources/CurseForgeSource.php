<?php

namespace Wyvern\Content\Sources;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Wyvern\Content\ContentFile;
use Wyvern\Content\ContentProject;
use Wyvern\Content\ContentType;
use Wyvern\Content\Contracts\ContentSource;
use Wyvern\Minecraft\Loader;

/**
 * CurseForge's v1 API.
 *
 * Unlike Modrinth this needs a key, and not one you can simply generate: their docs say
 * a third-party modding service has to apply for one, and that accounts which are not
 * game developers and did not request a key get deleted. Without a key every endpoint
 * answers 403, which is why isAvailable() gates the whole source — the picker hides it
 * rather than offering something that cannot work.
 *
 * Note this was written from CurseForge's published documentation, not from an observed
 * response, because no key was available to make one. The shapes below follow their
 * schema; treat the first real call as the thing that confirms them.
 *
 * One behaviour worth knowing before building on it: an author can forbid third-party
 * distribution, and the API then returns the file with downloadUrl null. ContentFile
 * carries that as a nullable url so the UI can say why a download is not offered rather
 * than failing at the point of install.
 */
class CurseForgeSource implements ContentSource
{
    private const BASE = 'https://api.curseforge.com/v1';

    /** Minecraft. */
    private const GAME_ID = 432;

    /** Their class ids for the three things we install. */
    private const CLASS_MODS = 6;

    private const CLASS_PLUGINS = 5;

    private const CLASS_MODPACKS = 4471;

    public function key(): string
    {
        return 'curseforge';
    }

    public function label(): string
    {
        return 'CurseForge';
    }

    public function isAvailable(): bool
    {
        return filled(config('wyvern.content.curseforge_key'));
    }

    public function search(
        string $query,
        ContentType $type,
        ?Loader $loader = null,
        ?string $gameVersion = null,
        int $limit = 24,
        string $sort = 'relevance',
        ?string $category = null,
    ): array {
        if (!$this->isAvailable()) {
            return [];
        }

        $params = array_filter([
            'gameId' => self::GAME_ID,
            'classId' => $this->classFor($type),
            'searchFilter' => $query ?: null,
            'gameVersion' => $gameVersion ?: null,
            'modLoaderType' => $this->loaderId($loader),
            // Their sort fields: 2 popularity, 3 last updated, 6 total downloads, 11 release date.
            'sortField' => match ($sort) {
                'updated' => 3,
                'newest' => 11,
                'downloads' => 6,
                default => $query !== '' ? 2 : 6,
            },
            'sortOrder' => 'desc',
            'pageSize' => $limit,
        ], fn ($v) => $v !== null);

        $response = $this->client()->get(self::BASE . '/mods/search', $params);

        if ($response->failed()) {
            return [];
        }

        return array_map(
            fn (array $mod): ContentProject => new ContentProject(
                source: $this->key(),
                id: (string) $mod['id'],
                title: $mod['name'] ?? '',
                summary: $mod['summary'] ?? '',
                type: $type,
                downloads: (int) ($mod['downloadCount'] ?? 0),
                iconUrl: $mod['logo']['thumbnailUrl'] ?? null,
                author: $mod['authors'][0]['name'] ?? null,
                pageUrl: $mod['links']['websiteUrl'] ?? null,
                categories: array_column($mod['categories'] ?? [], 'name'),
            ),
            $response->json('data', []),
        );
    }

    public function files(
        string $projectId,
        ?Loader $loader = null,
        ?string $gameVersion = null,
        int $limit = 20,
    ): array {
        if (!$this->isAvailable()) {
            return [];
        }

        $response = $this->client()->get(self::BASE . "/mods/{$projectId}/files", array_filter([
            'gameVersion' => $gameVersion ?: null,
            'modLoaderType' => $this->loaderId($loader),
            'pageSize' => $limit,
        ], fn ($v) => $v !== null));

        if ($response->failed()) {
            return [];
        }

        return array_map(
            fn (array $file): ContentFile => new ContentFile(
                source: $this->key(),
                id: (string) $file['id'],
                filename: $file['fileName'] ?? '',
                // Null when the author has opted out of third-party distribution.
                url: $file['downloadUrl'] ?? null,
                versionName: $file['displayName'] ?? '',
                gameVersions: $file['gameVersions'] ?? [],
                loaders: [],
                size: $file['fileLength'] ?? null,
                releaseType: match ($file['releaseType'] ?? null) {
                    1 => 'release',
                    2 => 'beta',
                    3 => 'alpha',
                    default => null,
                },
            ),
            $response->json('data', []),
        );
    }

    private function classFor(ContentType $type): int
    {
        return match ($type) {
            ContentType::Plugin => self::CLASS_PLUGINS,
            ContentType::Mod => self::CLASS_MODS,
            ContentType::Modpack => self::CLASS_MODPACKS,
        };
    }

    /** Their numeric loader ids. Null means "do not narrow". */
    private function loaderId(?Loader $loader): ?int
    {
        return match ($loader) {
            Loader::Forge => 1,
            Loader::Fabric => 4,
            Loader::Quilt => 5,
            Loader::NeoForge => 6,
            default => null,
        };
    }

    private function client(): PendingRequest
    {
        return Http::timeout(12)->withHeaders([
            'x-api-key' => (string) config('wyvern.content.curseforge_key'),
            'Accept' => 'application/json',
        ]);
    }
}
