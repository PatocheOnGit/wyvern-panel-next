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
 * Modrinth's v2 API.
 *
 * No key needed, but their docs ask every client to identify itself in the User-Agent
 * and rate-limit accordingly — so we do, with a contact address, rather than pretending
 * to be a browser.
 *
 * Search narrows through facets: an inner array is OR, the outer array is AND. So
 * [["categories:paper","categories:bukkit"],["versions:1.21.4"]] reads as "runs on
 * paper or bukkit, and on 1.21.4".
 */
class ModrinthSource implements ContentSource
{
    private const BASE = 'https://api.modrinth.com/v2';

    public function key(): string
    {
        return 'modrinth';
    }

    public function label(): string
    {
        return 'Modrinth';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(
        string $query,
        ContentType $type,
        ?Loader $loader = null,
        ?string $gameVersion = null,
        int $limit = 24,
    ): array {
        $facets = [[$this->projectTypeFacet($type)]];

        if ($loader && $type !== ContentType::Modpack) {
            $loaders = $loader->contentLoaders();

            if ($loaders !== []) {
                $facets[] = array_map(fn (string $l): string => "categories:{$l}", $loaders);
            }
        }

        if (filled($gameVersion)) {
            $facets[] = ["versions:{$gameVersion}"];
        }

        $response = $this->client()->get(self::BASE . '/search', [
            'query' => $query,
            'facets' => json_encode($facets),
            'index' => filled($query) ? 'relevance' : 'downloads',
            'limit' => $limit,
        ]);

        if ($response->failed()) {
            return [];
        }

        $hits = $response->json('hits', []);

        // A search run for a server should not offer client mods. Every hit already
        // carries server_side, so this costs nothing — and without it the first page of
        // results for a mod loader is mostly shaders and rendering engines that stop the
        // server booting at all.
        if ($type !== ContentType::Modpack) {
            $hits = array_filter(
                $hits,
                fn (array $hit): bool => ($hit['server_side'] ?? 'optional') !== 'unsupported',
            );
        }

        return array_map(
            fn (array $hit): ContentProject => new ContentProject(
                source: $this->key(),
                id: $hit['slug'] ?? $hit['project_id'],
                title: $hit['title'] ?? '',
                summary: $hit['description'] ?? '',
                type: $type,
                downloads: (int) ($hit['downloads'] ?? 0),
                iconUrl: $hit['icon_url'] ?: null,
                author: $hit['author'] ?? null,
                pageUrl: 'https://modrinth.com/' . ($hit['project_type'] ?? 'mod') . '/' . ($hit['slug'] ?? ''),
                categories: $hit['categories'] ?? [],
            ),
            $hits,
        );
    }

    public function files(
        string $projectId,
        ?Loader $loader = null,
        ?string $gameVersion = null,
        int $limit = 20,
    ): array {
        $query = [];

        if ($loader && $loader->contentLoaders() !== []) {
            $query['loaders'] = json_encode($loader->contentLoaders());
        }

        if (filled($gameVersion)) {
            $query['game_versions'] = json_encode([$gameVersion]);
        }

        $response = $this->client()->get(self::BASE . "/project/{$projectId}/version", $query);

        if ($response->failed()) {
            return [];
        }

        $files = [];

        foreach (array_slice($response->json() ?? [], 0, $limit) as $version) {
            // A release can ship several files; the primary one is the artefact, the
            // rest are sources and javadocs nobody wants on a server.
            $file = collect($version['files'] ?? [])->firstWhere('primary', true)
                ?? ($version['files'][0] ?? null);

            if (!$file) {
                continue;
            }

            $files[] = new ContentFile(
                source: $this->key(),
                id: $version['id'],
                filename: $file['filename'],
                url: $file['url'] ?? null,
                versionName: $version['version_number'] ?? $version['name'] ?? '',
                gameVersions: $version['game_versions'] ?? [],
                loaders: $version['loaders'] ?? [],
                size: $file['size'] ?? null,
                releaseType: $version['version_type'] ?? null,
            );
        }

        return $files;
    }

    /**
     * Worth knowing: the search index and the project endpoint disagree. /project/chunky
     * reports project_type "mod", but searching for it with project_type:mod AND a
     * Bukkit-family loader returns nothing at all — the index files those under
     * "plugin". Faceting on the value the project endpoint reports is the wrong guess,
     * and it fails silently as an empty result rather than an error.
     */
    /**
     * What each project says about running on a server, keyed by project id.
     *
     * A modpack's index states this per file, but pack authors get it wrong: one
     * optimisation pack tested here declared Sodium — a client renderer — as
     * server-required, and installing it stopped the server booting at all. The
     * project's own metadata is the better authority.
     *
     * @param  string[]  $projectIds
     * @return array<string, string> required | optional | unsupported
     */
    public function serverSupport(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_filter($projectIds)));

        if ($projectIds === []) {
            return [];
        }

        $support = [];

        // Their bulk endpoint takes the ids as a JSON array in the query string.
        foreach (array_chunk($projectIds, 100) as $chunk) {
            $response = $this->client()->get(self::BASE . '/projects', [
                'ids' => json_encode($chunk),
            ]);

            if ($response->failed()) {
                continue;
            }

            foreach ($response->json() ?? [] as $project) {
                $support[$project['id']] = $project['server_side'] ?? 'optional';
            }
        }

        return $support;
    }

    private function projectTypeFacet(ContentType $type): string
    {
        return match ($type) {
            ContentType::Plugin => 'project_type:plugin',
            ContentType::Mod => 'project_type:mod',
            ContentType::Modpack => 'project_type:modpack',
        };
    }

    private function client(): PendingRequest
    {
        return Http::timeout(12)
            ->withHeaders([
                'User-Agent' => config('wyvern.content.user_agent'),
            ]);
    }
}
