<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

/**
 * PaperMC's Fill v3 API.
 *
 * The project index groups versions by family ("26.2" => ["26.2", "26.2-rc-2"]), and a
 * build carries its own download entry keyed "server:default" with a sha256 — which is
 * worth passing on, since it is the only flavour that publishes one.
 */
class PaperCatalogue extends CachedCatalogue implements LoaderCatalogue
{
    private const BASE = 'https://fill.papermc.io/v3/projects/paper';

    public function loader(): Loader
    {
        return Loader::Paper;
    }

    public function gameVersions(): array
    {
        return $this->remember('versions', function (): array {
            $response = Http::timeout(10)->get(self::BASE);

            if ($response->failed()) {
                return [];
            }

            $families = $response->json('versions', []);

            // Flatten the families, dropping release candidates and pre-releases: a
            // server picker should not offer something upstream itself calls unfinished.
            return collect($families)
                ->flatten()
                ->reject(fn (string $v): bool => str_contains($v, '-rc') || str_contains($v, '-pre'))
                ->values()
                ->all();
        });
    }

    public function builds(string $gameVersion): array
    {
        return $this->remember("builds.{$gameVersion}", function () use ($gameVersion): array {
            $response = Http::timeout(10)->get(self::BASE . "/versions/{$gameVersion}/builds");

            if ($response->failed()) {
                return [];
            }

            return collect($response->json() ?? [])
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all();
        });
    }

    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary
    {
        return $this->remember("binary.{$gameVersion}." . ($build ?? 'latest'), function () use ($gameVersion, $build): ?ServerBinary {
            $response = Http::timeout(10)->get(self::BASE . "/versions/{$gameVersion}/builds");

            if ($response->failed()) {
                return null;
            }

            $builds = collect($response->json() ?? []);
            $entry = $build === null
                ? $builds->first()
                : $builds->firstWhere('id', (int) $build);

            $download = $entry['downloads']['server:default'] ?? null;

            if (!$download) {
                return null;
            }

            return new ServerBinary(
                loader: Loader::Paper,
                gameVersion: $gameVersion,
                build: (string) $entry['id'],
                url: $download['url'],
                sha256: $download['checksums']['sha256'] ?? null,
                size: $download['size'] ?? null,
            );
        });
    }
}
